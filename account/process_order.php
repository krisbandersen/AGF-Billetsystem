<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php';

if (!isset($_SESSION['user_id'])) {
    setFlashMessage('error', 'Du skal være logget ind for at placere en ordre.');
    redirect('../login.php');
    exit;
}
$userId = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect('../index.php');
    exit;
}

$items = $_POST['items'] ?? [];
$submittedTotalPrice = $_POST['total_price'] ?? 0;

if (empty($items) || !is_array($items)) {
    setFlashMessage('error', 'Ingen varer fundet i ordren.');
    redirect('../cart.php');
    exit;
}

$calculatedTotal = 0.00;
$dbItems = [];
$pdo = null;

try {
    $pdo = getDBConnection();

    foreach ($items as $item) {
        if (!isset($item['type']) || !isset($item['price'])) {
            throw new Exception("Ugyldigt vareformat modtaget.");
        }

        $price = filter_var($item['price'], FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) {
            throw new Exception("Ugyldig pris fundet for vare: " . htmlspecialchars($item['type']));
        }

        $calculatedTotal += $price;

        if ($item['type'] === 'ticket' && isset($item['match_id'], $item['ticket_type'])) {
            $matchId = filter_var($item['match_id'], FILTER_VALIDATE_INT);
            $seatNumber = isset($item['seat_number']) && $item['seat_number'] !== '' ? filter_var($item['seat_number'], FILTER_VALIDATE_INT) : null;
            if (isset($item['seat_number']) && $item['seat_number'] !== '' && $seatNumber === false) {
                throw new Exception("Ugyldigt sædenummer format.");
            }

            $dbItems[] = [
                'type' => 'ticket',
                'match_id' => $matchId,
                'ticket_type' => trim(htmlspecialchars($item['ticket_type'])),
                'price' => $price,
                'section' => trim(htmlspecialchars($item['section'] ?? null)) ?: null,
                'row' => trim(htmlspecialchars($item['row'] ?? null)) ?: null,
                'seat_number' => $seatNumber,
            ];
        } elseif ($item['type'] === 'subscription' && isset($item['subscription_type_id'])) {
            $subTypeId = filter_var($item['subscription_type_id'], FILTER_VALIDATE_INT);
            if (!$subTypeId) {
                throw new Exception("Ugyldigt abonnements type ID.");
            }
            $sqlSubInfo = "SELECT billing_interval FROM subscription_types WHERE subscription_type_id = :id";
            $subInfoResult = executeQuery($sqlSubInfo, ['id' => $subTypeId]);
            $billingInterval = $subInfoResult[0]['billing_interval'] ?? null;

            $dbItems[] = [
                'type' => 'subscription',
                'subscription_type_id' => $subTypeId,
                'price' => $price,
                'billing_interval' => $billingInterval
            ];
        } else {
            throw new Exception("Ukendt eller ufuldstændig varetype modtaget: " . htmlspecialchars($item['type']));
        }
    }
} catch (Exception $e) {
    logEvent('ERROR', "Order processing error (User: $userId): Input validation failed - " . $e->getMessage(), $userId);
    setFlashMessage('error', 'Fejl i ordre data: ' . $e->getMessage());
    redirect('../cart.php');
    exit;
}

$newOrderId = null;
if ($pdo) {
    try {
        $pdo->beginTransaction();

        $sqlOrder = "INSERT INTO orders (user_id, order_date, total_price, payment_status) VALUES (:userid, NOW(), :total, :status)";
        $orderParams = [
            'userid' => $userId,
            'total' => $calculatedTotal,
            'status' => 'Completed'
        ];
        $stmtOrder = $pdo->prepare($sqlOrder);
        if (!$stmtOrder->execute($orderParams)) {
            throw new PDOException("Failed to insert order.");
        }
        $newOrderId = $pdo->lastInsertId();
        if (!$newOrderId) {
            throw new PDOException("Failed to retrieve new order ID.");
        }

        $sqlPayment = "INSERT INTO payments (order_id, payment_date, amount, payment_method, payment_status) VALUES (:orderid, NOW(), :amount, :method, :status)";
        $paymentParams = [
            'orderid' => $newOrderId,
            'amount' => $calculatedTotal,
            'method' => 'Simulated Test Payment',
            'status' => 'Completed'
        ];
        $stmtPayment = $pdo->prepare($sqlPayment);
        if (!$stmtPayment->execute($paymentParams)) {
            throw new PDOException("Failed to insert payment record.");
        }

        foreach ($dbItems as $item) {
            if ($item['type'] === 'ticket') {
                $sqlTicket = "INSERT INTO tickets (order_id, match_id, ticket_type, price, section, `row`, seat_number)
                              VALUES (:orderid, :matchid, :tickettype, :price, :section, :row, :seatnumber)";
                $ticketParams = [
                    'orderid' => $newOrderId,
                    'matchid' => $item['match_id'],
                    'tickettype' => $item['ticket_type'],
                    'price' => $item['price'],
                    'section' => $item['section'],
                    'row' => $item['row'],
                    'seatnumber' => $item['seat_number']
                ];

                if (empty($ticketParams['matchid']) || empty($ticketParams['tickettype'])) {
                    throw new PDOException("Missing required ticket information (Match ID or Type).");
                }

                $stmtTicket = $pdo->prepare($sqlTicket);
                if (!$stmtTicket->execute($ticketParams)) {
                    if ($stmtTicket->errorCode() == '23000') {
                        $errorInfo = $stmtTicket->errorInfo();
                        $errorMessage = $errorInfo[2] ?? "Seat conflict";
                        $seatDetails = $item['section'] ? " ({$item['section']}-{$item['row']}-{$item['seat_number']})" : "";
                        throw new PDOException("Fejl: Det valgte sæde{$seatDetails} er muligvis allerede reserveret.");
                    }
                    throw new PDOException("Failed to insert ticket record. Error code: " . $stmtTicket->errorCode());
                }
            } elseif ($item['type'] === 'subscription') {
                $sqlSubscription = "INSERT INTO user_subscriptions (user_id, subscription_type_id, start_date, end_date, status, auto_renew, last_payment_date)
                                    VALUES (:userid, :subtypeid, :startdate, :enddate, :status, :autorenew, :lastpayment)";
                $startDate = date('Y-m-d');
                $endDate = null;
                $autoRenew = 1;

                switch ($item['billing_interval']) {
                    case 'monthly':
                        $endDate = date('Y-m-d', strtotime('+1 month'));
                        break;
                    case 'annually':
                        $endDate = date('Y-m-d', strtotime('+1 year'));
                        break;
                    case 'one-time':
                        $currentYear = date('Y');
                        $cutoffMonth = 7;
                        $seasonEndYear = (date('n') >= $cutoffMonth) ? $currentYear + 1 : $currentYear;
                        $endDate = $seasonEndYear . '-06-30';
                        $autoRenew = 0;
                        break;
                    default:
                        $endDate = null;
                        $autoRenew = 0;
                }

                $subscriptionParams = [
                    'userid' => $userId,
                    'subtypeid' => $item['subscription_type_id'],
                    'startdate' => $startDate,
                    'enddate' => $endDate,
                    'status' => 'active',
                    'autorenew' => $autoRenew,
                    'lastpayment' => date('Y-m-d H:i:s')
                ];

                if (empty($subscriptionParams['subtypeid'])) {
                    throw new PDOException("Missing required subscription information (Type ID).");
                }

                $stmtSubscription = $pdo->prepare($sqlSubscription);
                if (!$stmtSubscription->execute($subscriptionParams)) {
                    throw new PDOException("Failed to insert user subscription record.");
                }
            }
        }

        $pdo->commit();

        logEvent('INFO', "Order successfully processed (User: $userId, Order ID: $newOrderId)", $userId);
        unset($_SESSION['cart']);
        redirect("order_confirmation.php?order_id=" . $newOrderId);
        exit;
    } catch (PDOException | Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $e->getMessage();
        logEvent('ERROR', "Order processing FAILED (User: $userId, Attempted Order): $errorMessage", $userId);
        $userFriendlyError = strpos($errorMessage, 'Det valgte sæde') !== false ? $errorMessage : 'Der opstod en fejl under behandlingen af din ordre. Prøv venligst igen.';
        setFlashMessage('error', $userFriendlyError);
        redirect('../cart.php');
        exit;
    }
} else {
    logEvent('ERROR', "Order processing FAILED (User: $userId): Database connection could not be established", $userId);
    setFlashMessage('error', 'Database forbindelse fejlede. Kunne ikke behandle ordren.');
    redirect('../cart.php');
    exit;
}
?>