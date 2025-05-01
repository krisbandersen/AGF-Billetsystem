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
$userSubscriptions = []; // Initialize array for user's subscriptions
$fetchError = null; // Variable to store potential errors

// --- Fetch User Subscriptions ---
try {
    $sql = "SELECT
                us.user_subscription_id,
                us.start_date,
                us.end_date,
                us.status,
                us.auto_renew,
                st.name AS subscription_name,
                st.description AS subscription_description,
                st.price,
                st.billing_interval
            FROM user_subscriptions us
            JOIN subscription_types st ON us.subscription_type_id = st.subscription_type_id
            WHERE us.user_id = :user_id
            ORDER BY FIELD(us.status, 'active', 'pending', 'cancelled', 'expired'), us.end_date DESC, us.start_date DESC";

    $userSubscriptions = executeQuery($sql, ['user_id' => $userId]);

    // Check if executeQuery returned false (indicating an error)
    if ($userSubscriptions === false) {
        $fetchError = "Database query failed.";
        $userSubscriptions = []; // Ensure it's an empty array on error
    }

} catch (Exception $e) {
    $fetchError = "An exception occurred while fetching subscriptions.";
    error_log("Error fetching subscriptions for user ID {$userId}: " . $e->getMessage());
    $userSubscriptions = []; // Ensure it's an empty array on exception
}


// --- Define Navigation Items ---
$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Transaktioner' => 'transactions.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
    'Admin' => 'admin.php',
];

$currentPage = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-key="pageTitleSubscriptions">AGF - Min Konto - Mine Abonnementer</title>
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
        /* Style for different subscription statuses */
        .status-badge {
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
        .status-active { background-color: #10b981; } /* Emerald 500 */
        .status-cancelled { background-color: #f97316; } /* Orange 500 */
        .status-expired { background-color: #6b7280; } /* Gray 500 */
        .status-pending { background-color: #f59e0b; } /* Amber 500 */
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

            <!-- Subscriptions Section -->
            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-4" data-lang-key="subscriptionsHeading">Mine Abonnementer</h2>

                 <?php if ($fetchError): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline" data-lang-key="subscriptionFetchError">Kunne ikke hente dine abonnementer. Prøv igen senere.</span>
                    </div>
                <?php elseif (empty($userSubscriptions)): ?>
                    <p class="text-gray-600" data-lang-key="noSubscriptionsFound">Du har ingen aktive eller tidligere abonnementer.</p>
                    <!-- Optional: Add a link/button to browse available subscriptions -->
                    <!--
                    <div class="mt-4">
                        <a href="/subscribe" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Se Abonnementer
                        </a>
                    </div>
                    -->
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($userSubscriptions as $sub): ?>
                            <?php
                                // Sanitize data
                                $subName = htmlspecialchars($sub['subscription_name'] ?? 'Ukendt Abonnement');
                                $subDesc = htmlspecialchars($sub['subscription_description'] ?? '');
                                $status = htmlspecialchars($sub['status'] ?? 'unknown');
                                $autoRenew = (bool)($sub['auto_renew'] ?? false);

                                // Format dates
                                $startDateFormatted = !empty($sub['start_date']) ? date('d/m/Y', strtotime($sub['start_date'])) : 'N/A';
                                $endDateFormatted = !empty($sub['end_date']) ? date('d/m/Y', strtotime($sub['end_date'])) : 'N/A';

                                // Determine display text for status and auto-renew
                                switch ($status) {
                                    case 'active':
                                        $statusText = 'Aktiv';
                                        break;
                                    case 'cancelled':
                                        $statusText = 'Opsagt';
                                        break;
                                    case 'expired':
                                        $statusText = 'Udløbet';
                                        break;
                                    case 'pending':
                                        $statusText = 'Afventer';
                                        break;
                                    default:
                                        $statusText = ucfirst($status);
                                        break;
                                };

                                $autoRenewText = $autoRenew ? 'Ja' : 'Nej';
                                $endDateLabel = ($status === 'active' && $autoRenew) ? 'Fornyes d.' : 'Udløber d.';
                                if ($status !== 'active') $endDateLabel = 'Slutdato:';
                                if ($endDateFormatted === 'N/A' && $status === 'active' && $sub['billing_interval'] === 'monthly') $endDateLabel = 'Næste betaling:'; // Adjust logic as needed
                                if ($endDateFormatted === 'N/A' && $status === 'active' && $sub['billing_interval'] !== 'monthly') $endDateFormatted = 'Løbende'; // If no end date and not monthly

                                // Get status badge class
                                $statusClass = 'status-' . $status;
                            ?>
                            <div class="p-4 border border-gray-200 rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow duration-200">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-start">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 mb-1"><?php echo $subName; ?></h3>
                                        <p class="text-sm text-gray-600 mb-2"><?php echo $subDesc; ?></p>
                                    </div>
                                    <span class="status-badge <?php echo $statusClass; ?> mt-2 sm:mt-0 sm:ml-4"><?php echo $statusText; ?></span>
                                </div>
                                <div class="mt-3 text-sm text-gray-700 space-y-1">
                                    <p><strong class="font-medium w-28 inline-block">Startdato:</strong> <?php echo $startDateFormatted; ?></p>
                                    <?php if ($endDateFormatted !== 'Løbende' || $status !== 'active'): // Show end date if not active or if it has one ?>
                                        <p><strong class="font-medium w-28 inline-block"><?php echo $endDateLabel; ?></strong> <?php echo $endDateFormatted; ?></p>
                                    <?php endif; ?>
                                     <?php if ($status === 'active'): // Show auto-renew only for active ?>
                                        <p><strong class="font-medium w-28 inline-block">Auto-fornyelse:</strong> <?php echo $autoRenewText; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-200 flex space-x-2">
                                    <!-- Add buttons based on status -->
                                     <?php if ($status === 'active'): ?>
                                        <button class="px-3 py-1 bg-gray-500 text-white text-xs rounded hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50" disabled>
                                            <i class="fas fa-cog mr-1"></i>Administrer (Kommer snart)
                                        </button>
                                        <?php if ($autoRenew): ?>
                                            <button class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50" disabled>
                                                <i class="fas fa-times-circle mr-1"></i>Opsig (Kommer snart)
                                            </button>
                                        <?php endif; ?>
                                     <?php elseif ($status === 'cancelled' || $status === 'expired'): ?>
                                         <!-- Option to resubscribe? -->
                                          <button class="px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" disabled>
                                            <i class="fas fa-redo mr-1"></i>Forny (Kommer snart)
                                        </button>
                                     <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                     // Optional: Store preference
                });
            });
      }
      // TODO: Add updateTranslations function if needed for this page specifically
      // TODO: Add JavaScript logic for Manage/Cancel/Renew buttons when functionality is implemented
    </script>
</body>
</html>