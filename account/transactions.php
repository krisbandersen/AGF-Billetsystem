<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php'; // Adjust path if needed

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    redirect('../login.php'); // Redirect to login if not logged in
    exit;
}

$userId = $_SESSION['user_id'];
$transactions = []; // Initialize array for user's transactions
$fetchError = null; // Variable to store potential errors

// --- Fetch User Transactions ---
try {
    // Fetch payments linked to the user's orders
    $sql = "SELECT
                p.payment_id,
                p.payment_date,
                p.amount,
                p.payment_method,
                p.payment_status,
                p.order_id,
                o.order_date -- Also get the order date for reference
            FROM payments p
            JOIN orders o ON p.order_id = o.order_id
            WHERE o.user_id = :user_id
            ORDER BY p.payment_date DESC"; // Show most recent first

    $transactions = executeQuery($sql, ['user_id' => $userId]);

    // Check if executeQuery returned false (indicating an error)
    if ($transactions === false) {
        $fetchError = "Database query failed.";
        $transactions = []; // Ensure it's an empty array on error
        logEvent('ERROR', "Error fetching transactions for user ID {$userId}: executeQuery returned false.");
    }

} catch (Exception $e) {
    $fetchError = "An exception occurred while fetching transactions.";
    error_log("Error fetching transactions for user ID {$userId}: " . $e->getMessage());
    logEvent('ERROR', "Exception fetching transactions for user ID {$userId}: " . $e->getMessage());
    $transactions = []; // Ensure it's an empty array on exception
}


$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Transaktioner' => 'transactions.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
    'Admin' => 'admin.php',
];

$currentPage = basename($_SERVER['PHP_SELF']); // Will be 'transactions.php'

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-key="pageTitleTransactions">AGF - Min Konto - Mine Transaktioner</title>
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
        .account-nav-active {
          color: #2563eb;
          font-weight: 600;
          border-bottom: 2px solid #2563eb;
        }
         /* Style for payment statuses */
        .payment-status-badge {
            display: inline-block;
            padding: 0.2em 0.6em;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        /* Add specific colors as needed */
        .payment-completed { background-color: #10b981; } /* Emerald 500 */
        .payment-pending { background-color: #f59e0b; } /* Amber 500 */
        .payment-failed { background-color: #ef4444; } /* Red 500 */
        .payment-refunded { background-color: #6b7280; } /* Gray 500 */
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include '../layout/navbar.php'; // Adjust path if needed ?>

    <main class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
            <!-- Account Navigation -->
            <nav class="mb-6 pb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                    <?php foreach ($accountNavItems as $title => $file): ?>
                        <li class="mr-2">
                            <?php
                                $isActive = ($currentPage == $file);
                                $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 ';
                                $linkClasses .= $isActive ? 'text-blue-600 border-blue-600 active' : 'border-transparent hover:text-gray-600 hover:border-gray-300';
                            ?>
                            <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>" <?php if($isActive) echo 'aria-current="page"'; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- Transactions Section -->
            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-4" data-lang-key="transactionsHeading">Mine Transaktioner</h2>

                 <?php if ($fetchError): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline" data-lang-key="transactionFetchError">Kunne ikke hente dine transaktioner. Prøv igen senere.</span>
                    </div>
                <?php elseif (empty($transactions)): ?>
                    <p class="text-gray-600" data-lang-key="noTransactionsFound">Du har ingen registrerede transaktioner.</p>
                <?php else: ?>
                    <div class="overflow-x-auto"> <!-- Make table scrollable on small screens -->
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Dato
                                    </th>
                                     <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ordre ID
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Metode
                                    </th>
                                     <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Beløb
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="relative px-4 py-3">
                                        <span class="sr-only">Vis Ordre</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($transactions as $trans): ?>
                                    <?php
                                        $paymentDate = !empty($trans['payment_date']) ? date('d/m/Y H:i', strtotime($trans['payment_date'])) : 'N/A';
                                        $orderIdLink = htmlspecialchars($trans['order_id']);
                                        $paymentMethod = htmlspecialchars($trans['payment_method'] ?? 'Ukendt');
                                        $amount = number_format((float)($trans['amount'] ?? 0), 2, ',', '.');
                                        $status = strtolower(htmlspecialchars($trans['payment_status'] ?? 'unknown')); // Lowercase for class matching
                                        $statusText = htmlspecialchars($trans['payment_status'] ?? 'Ukendt');

                                        $statusClass = ''; // Initialize variable
                                         switch ($status) {
                                             case 'completed':
                                                 $statusClass = 'payment-completed';
                                                 break;
                                             case 'pending':
                                                 $statusClass = 'payment-pending';
                                                 break;
                                             case 'failed':
                                             case 'fejlet': // Handle potential variations in status string
                                                 $statusClass = 'payment-failed';
                                                 break;
                                             case 'refunded':
                                                 $statusClass = 'payment-refunded';
                                                 break;
                                             default:
                                                 $statusClass = 'bg-gray-400'; // Default grey badge
                                                 break;
                                         }
                                    ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo $paymentDate; ?></td>
                                         <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">#<?php echo $orderIdLink; ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $paymentMethod; ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 text-right"><?php echo $amount; ?> kr.</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-center">
                                             <span class="payment-status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="order_confirmation.php?order_id=<?php echo $orderIdLink; ?>" class="text-blue-600 hover:text-blue-800" title="Vis Ordre Detaljer">
                                                 <i class="fas fa-eye"></i> <span class="hidden sm:inline">Vis</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../layout/footer.php'; // Adjust path if needed ?>

    <script>
      // --- Language Dropdown Logic (Same as other account pages) ---
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
      // TODO: Add updateTranslations function if needed for this page specifically
    </script>
</body>
</html>