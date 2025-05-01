<?php
// --- START: order_confirmation.php ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php'; // Adjust path - assumes confirmation is in account/

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    // Although unlikely to reach here without logging in to order, check anyway
    redirect('../login.php');
    exit;
}
$userId = $_SESSION['user_id'];

// --- Get Order ID from URL and Validate ---
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId) {
    setFlashMessage('error', 'Ugyldigt eller manglende ordre ID.');
    redirect('tickets.php'); // Redirect to a default account page
    exit;
}

// --- Fetch Order Details ---
$orderDetails = null;
$orderItemsTickets = [];
$orderItemsSubscriptions = []; // Still unreliable without schema change
$fetchError = null;

try {
    $pdo = getDBConnection();

    // Fetch main order details - Ensure this order belongs to the logged-in user!
    $sqlOrder = "SELECT * FROM orders WHERE order_id = :orderid AND user_id = :userid";
    $stmtOrder = $pdo->prepare($sqlOrder);
    $stmtOrder->execute(['orderid' => $orderId, 'userid' => $userId]);
    $orderDetails = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$orderDetails) {
        setFlashMessage('error', 'Ordren blev ikke fundet eller tilhører ikke din konto.');
        redirect('tickets.php');
        exit;
    }

    // Fetch associated tickets
    $sqlTickets = "SELECT t.*, m.opponent, m.match_datetime
                   FROM tickets t
                   JOIN matches m ON t.match_id = m.match_id
                   WHERE t.order_id = :orderid
                   ORDER BY m.match_datetime";
    $stmtTickets = $pdo->prepare($sqlTickets);
    $stmtTickets->execute(['orderid' => $orderId]);
    $orderItemsTickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

    // Fetch potentially associated subscriptions (heuristic)
     $sqlSubscriptions = "SELECT us.*, st.name as subscription_name
                         FROM user_subscriptions us
                         JOIN subscription_types st ON us.subscription_type_id = st.subscription_type_id
                         WHERE us.user_id = :userid
                         AND us.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) -- Created recently
                         ORDER BY us.created_at DESC"; // Get newest first
     $stmtSubscriptions = $pdo->prepare($sqlSubscriptions);
     $stmtSubscriptions->execute(['userid' => $userId]);
     $allRecentSubs = $stmtSubscriptions->fetchAll(PDO::FETCH_ASSOC);
     // Count subscriptions created recently as an indicator
     $subscriptionCountInOrder = count($allRecentSubs);


} catch (Exception $e) {
    $fetchError = "Fejl ved hentning af ordredetaljer.";
    logEvent('ERROR', "Error fetching confirmation details for Order ID {$orderId} (User: {$userId}): " . $e->getMessage());
}


// --- Define Navigation Items ---
$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Betalingsmetoder' => 'payment-methods.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
];
// Confirmation isn't strictly part of the nav items
$currentPage = '';

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordrebekræftelse - AGF</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        #language-menu {
            transition: opacity 0.2s ease-out, transform 0.2s ease-out;
        }
        #language-menu.hidden {
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
        }
        /* Style for active nav item */
        .account-nav-active {
          color: #2563eb;
          font-weight: 600;
          border-bottom: 2px solid #2563eb;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include '../layout/navbar.php'; // Assumes navbar.php is in layout/ ?>

    <main class="container mx-auto my-8 px-4">
        <!-- Apply the layout structure -->
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">

             <!-- Account Navigation (Included for layout consistency) -->
             <nav class="mb-6 pb-4 border-b border-gray-200">
                 <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                     <?php foreach ($accountNavItems as $title => $file): ?>
                         <li class="mr-2">
                              <?php
                                 // No item active on confirmation page
                                 $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300';
                             ?>
                             <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>">
                                 <?php echo htmlspecialchars($title); ?>
                             </a>
                         </li>
                     <?php endforeach; ?>
                 </ul>
             </nav>

            <!-- Confirmation Content -->
            <div class="mt-6">
                <div class="text-center mb-8">
                     <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Tak for din ordre!</h1>
                    <p class="text-gray-600">Din ordre er blevet behandlet succesfuldt.</p>
                </div>

                <?php if ($fetchError): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($fetchError); ?></span>
                        <p class="text-sm mt-1">Du kan se dine køb under <a href="tickets.php" class="font-medium underline">Mine Billetter</a> og <a href="subscriptions.php" class="font-medium underline">Mine Abonnementer</a>.</p>
                    </div>
                <?php elseif ($orderDetails): ?>
                    <div class="border border-gray-200 rounded-lg p-4 sm:p-6 mb-6 max-w-2xl mx-auto">
                        <h2 class="text-xl font-semibold mb-4 text-center sm:text-left">Ordre Oversigt</h2>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt class="font-medium text-gray-500">Ordre ID:</dt>
                            <dd class="text-gray-900">#<?php echo htmlspecialchars($orderDetails['order_id']); ?></dd>

                            <dt class="font-medium text-gray-500">Ordredato:</dt>
                            <dd class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($orderDetails['order_date'])); ?></dd>

                            <dt class="font-medium text-gray-500">Total Pris:</dt>
                            <dd class="text-gray-900 font-semibold"><?php echo number_format((float)$orderDetails['total_price'], 2, ',', '.'); ?> kr.</dd>

                            <dt class="font-medium text-gray-500">Status:</dt>
                            <dd class="text-gray-900"><?php echo htmlspecialchars($orderDetails['payment_status']); ?></dd>
                             <dt class="font-medium text-gray-500">Betalingsmetode:</dt>
                             <dd class="text-gray-900">Simulated Test Payment</dd> <!-- Hardcoded for test -->
                        </dl>
                    </div>

                    <div class="max-w-2xl mx-auto">
                        <h3 class="text-lg font-semibold mb-3">Købte Varer:</h3>

                        <?php if (!empty($orderItemsTickets)): ?>
                            <div class="space-y-3 mb-6">
                                <h4 class="text-md font-medium text-gray-700">Billetter:</h4>
                                <?php foreach($orderItemsTickets as $ticket): ?>
                                    <div class="p-3 border rounded-md bg-gray-50 text-sm">
                                        <?php
                                            $t_opponent = htmlspecialchars($ticket['opponent']);
                                            $t_datetime = !empty($ticket['match_datetime']) ? date('d/m/Y H:i', strtotime($ticket['match_datetime'])) : 'N/A';
                                            $t_type = htmlspecialchars($ticket['ticket_type']);
                                            $t_section = htmlspecialchars($ticket['section'] ?? '-');
                                            $t_row = htmlspecialchars($ticket['row'] ?? '-');
                                            $t_seat = htmlspecialchars($ticket['seat_number'] ?? '-');
                                            $t_price = number_format((float)$ticket['price'], 2, ',', '.');
                                        ?>
                                        <p class="font-medium">AGF - <?php echo $t_opponent; ?> <span class="text-xs text-gray-500">(<?php echo $t_datetime; ?>)</span></p>
                                        <p>Type: <?php echo $t_type; ?> (<?php echo $t_price; ?> kr.)</p>
                                        <p>Plads: Sektion <?php echo $t_section; ?>, Række <?php echo $t_row; ?>, Sæde <?php echo $t_seat; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                         <?php if ($subscriptionCountInOrder > 0): ?>
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-700">Abonnementer:</h4>
                                <p class="p-3 border rounded-md bg-gray-50 text-sm">
                                    Dit/dine nye abonnement(er) er aktiveret. Du kan se detaljerne under <a href="subscriptions.php" class="font-medium underline hover:text-blue-700">Mine Abonnementer</a>.
                                </p>
                            </div>
                        <?php endif; ?>


                        <div class="mt-8 text-center">
                             <a href="tickets.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3 mb-2 sm:mb-0">
                                Se Mine Billetter
                            </a>
                             <a href="subscriptions.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mb-2 sm:mb-0">
                                Se Mine Abonnementer
                            </a>
                            <a href="../index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ml-0 sm:ml-3">
                                Gå til Forsiden
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                     <p class="text-center text-red-600">Kunne ikke indlæse ordrebekræftelsen.</p>
                <?php endif; ?>

            </div> <!-- end .mt-6 -->
        </div> <!-- end .bg-white -->
    </main>

    <?php include '../layout/footer.php'; // Adjust path if needed ?>

    <script>
      // --- Language Dropdown Logic (if navbar included) ---
      const langButton = document.getElementById('language-button');
      const langMenu = document.getElementById('language-menu');
      const currentLangSpan = document.getElementById('current-language');
      const langOptions = document.querySelectorAll('.language-option');

      if (langButton && langMenu && currentLangSpan && langOptions.length > 0) {
            langButton.addEventListener('click', (event) => {
                event.stopPropagation();
                const isExpanded = langButton.getAttribute('aria-expanded') === 'true';
                langMenu.classList.toggle('hidden');
                langButton.setAttribute('aria-expanded', String(!isExpanded));
            });

            document.addEventListener('click', (event) => {
                if (!langButton.contains(event.target) && !langMenu.contains(event.target)) {
                    if (!langMenu.classList.contains('hidden')) {
                        langMenu.classList.add('hidden');
                        langButton.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            langOptions.forEach(option => {
                option.addEventListener('click', (event) => {
                    event.preventDefault();
                    const selectedLangCode = option.getAttribute('data-lang');
                    const selectedLangName = option.textContent;
                    currentLangSpan.textContent = selectedLangName;
                    // updateTranslations(selectedLangCode); // Assuming this function exists
                    document.documentElement.lang = selectedLangCode;
                    langMenu.classList.add('hidden');
                    langButton.setAttribute('aria-expanded', 'false');
                    langOptions.forEach(opt => opt.classList.remove('bg-gray-100', 'font-semibold'));
                    option.classList.add('bg-gray-100', 'font-semibold');
                });
            });
       }
    </script>
</body>
</html>