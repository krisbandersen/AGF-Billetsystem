<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php'; // Assumes utils.php is in php/

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    setFlashMessage('error', 'Du skal være logget ind for at tilføje varer til kurven.');
    redirect('../login.php'); // Redirect relative to this script's location
    exit;
}
$userId = $_SESSION['user_id']; // Get user ID for potential use

// --- Check Request Method and Input ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect('tickets.php'); // Redirect if not POST (relative to this script)
    exit;
}

// --- Validate Input ---
$itemType = $_POST['item_type'] ?? null;

// --- Handle Ticket Addition ---
if ($itemType === 'ticket') {
    $matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
    // Get section NAME from the form now
    $sectionName = isset($_POST['section']) ? trim(strtoupper(htmlspecialchars($_POST['section']))) : null;
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    // Get display title, potentially append section?
    $displayTitle = trim(htmlspecialchars($_POST['display_title'] ?? 'Kampbillet'));
    // Get ticket type (might be same as section name, or 'Voksen' etc.)
    $ticketType = isset($_POST['ticket_type']) ? trim(htmlspecialchars($_POST['ticket_type'])) : 'Voksen';


    // Basic Validation
    if (!$matchId || !$sectionName || $price === false || $price < 0) {
        setFlashMessage('error', 'Ugyldig data modtaget for billet (Match ID, Sektion, eller Pris mangler/er ugyldig).');
        // Redirect back to stadium page if possible, otherwise tickets page
        if ($matchId) {
             redirect("stadium.php?matchid=" . $matchId);
        } else {
             redirect('tickets.php');
        }
        exit;
    }

    // --- Verify Match and Section Validity (Optional but Recommended) ---
    try {
         $sqlCheckMatch = "SELECT opponent, stadium FROM matches WHERE match_id = :id";
         $matchResult = executeQuery($sqlCheckMatch, ['id' => $matchId]);
         if (empty($matchResult)) {
             setFlashMessage('error', 'Den valgte kamp findes ikke.');
             redirect('tickets.php');
             exit;
         }
         $matchInfo = $matchResult[0];
         $isHomeMatch = (stripos($matchInfo['stadium'], "Ceres Park") !== false); 
         if (!$isHomeMatch && $sectionName !== 'AWAY') { 

         }
         
         $validSections = ['ULTRA', 'FAMILY', 'AWAY', 'VIP']; // Example valid sections
         if (!in_array($sectionName, $validSections)) {
              setFlashMessage('error', 'Ugyldig sektion valgt.');
              redirect("stadium.php?matchid=" . $matchId);
              exit;
         }

    } catch (Exception $e) {
        logEvent('ERROR', "Error checking match/section existence (ID: {$matchId}, Section: {$sectionName}) in add_to_cart: " . $e->getMessage(), $userId, null, $matchId);
        setFlashMessage('error', 'Databasefejl under validering af kamp/sektion.');
        redirect('tickets.php');
        exit;
    }


    // --- Assign Seat and Row ---
    $assignedRow = null;
    $assignedSeat = null;

    // Determine row prefix based on section
    $rowPrefix = strtoupper(substr($sectionName, 0, 1)); // U, F, A, V etc.

    $maxSeatsToCheck = 500; // Prevent infinite loops if section is somehow full
    $seatNumber = 1;

    try {
        $pdo = getDBConnection(); // Get PDO connection

        while ($seatNumber <= $maxSeatsToCheck) {
            // Check if this specific seat is taken for this match/section/row
            $sqlCheckSeat = "SELECT 1 FROM tickets
                             WHERE match_id = :match_id
                             AND section = :section
                             AND `row` = :row
                             AND seat_number = :seat_number
                             LIMIT 1";
            $stmtCheck = $pdo->prepare($sqlCheckSeat);
            $paramsCheck = [
                'match_id' => $matchId,
                'section' => $sectionName,
                'row' => $rowPrefix, // Use the derived prefix
                'seat_number' => $seatNumber
            ];
            $stmtCheck->execute($paramsCheck);

            if ($stmtCheck->fetch() === false) {
                // Seat is available!
                $assignedRow = $rowPrefix;
                $assignedSeat = $seatNumber;
                break; // Exit the loop
            }

            // Seat taken, try the next one
            $seatNumber++;
        }

    } catch (PDOException $e) {
         logEvent('ERROR', "Database error during seat assignment (Match: {$matchId}, Section: {$sectionName}): " . $e->getMessage(), $userId, null, $matchId);
         setFlashMessage('error', 'Databasefejl under tildeling af plads.');
         redirect("stadium.php?matchid=" . $matchId);
         exit;
    }

    // Handle case where no seat was found (section potentially full or error)
    if ($assignedRow === null || $assignedSeat === null) {
        logEvent('WARNING', "Could not assign seat (section potentially full?) (Match: {$matchId}, Section: {$sectionName})", $userId, null, $matchId);
        setFlashMessage('error', "Kunne ikke finde en ledig plads i sektion '{$sectionName}'. Prøv venligst en anden sektion eller kontakt support.");
        redirect("stadium.php?matchid=" . $matchId);
        exit;
    }

    // --- Add to Cart Session ---
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cartItem = [
        'type' => 'ticket',
        'match_id' => $matchId,
        'ticket_type' => $ticketType, // Could be 'Voksen' or the section name depending on needs
        'price' => $price,
        // Add the ASSIGNED seating info
        'section' => $sectionName,
        'row' => $assignedRow,
        'seat_number' => $assignedSeat,
        // Update display title for clarity in cart
        'display_title' => $displayTitle . " (Sektion: {$sectionName}, Række: {$assignedRow}, Sæde: {$assignedSeat})",
    ];

    $_SESSION['cart'][] = $cartItem;

    setFlashMessage('success', "Billet til {$sectionName} (Rk: {$assignedRow}, Sæde: {$assignedSeat}) tilføjet til kurven.");
    redirect('cart.php'); // Redirect to cart page
    exit;

}
// --- Handle Subscription (Keep Existing Logic) ---
elseif ($itemType === 'subscription') {
    // ... (your existing subscription handling code) ...
     $subTypeId = filter_input(INPUT_POST, 'subscription_type_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $displayTitle = trim(htmlspecialchars($_POST['display_title'] ?? 'Abonnement'));

    if (!$subTypeId || $price === false || $price < 0) {
        setFlashMessage('error', 'Ugyldig data modtaget for abonnement.');
        redirect('tickets.php');
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cartItem = [
        'type' => 'subscription',
        'subscription_type_id' => $subTypeId,
        'price' => $price,
        'display_title' => $displayTitle,
    ];

    $_SESSION['cart'][] = $cartItem;

    setFlashMessage('success', htmlspecialchars($displayTitle) . ' tilføjet til kurven.');
    redirect('cart.php');
    exit;

} else {
    setFlashMessage('error', 'Ukendt varetype forsøgt tilføjet til kurven.');
    redirect('tickets.php');
    exit;
}
?>