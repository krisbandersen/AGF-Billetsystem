<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php'; // Path assumes cart.php is in the root directory

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    setFlashMessage('error', 'Du skal være logget ind for at se din kurv.');
    redirect('login.php');
    exit;
}
$userId = $_SESSION['user_id'];

// --- Handle Item Removal ---
if (isset($_GET['remove_item'])) {
    $itemIndexToRemove = filter_input(INPUT_GET, 'remove_item', FILTER_VALIDATE_INT);

    if ($itemIndexToRemove !== false && isset($_SESSION['cart'][$itemIndexToRemove])) {
        // Remove the item from the cart array
        unset($_SESSION['cart'][$itemIndexToRemove]);
        // Re-index the array to prevent gaps if needed (optional but good practice)
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        setFlashMessage('success', 'Vare fjernet fra kurven.');
    } else {
        setFlashMessage('error', 'Ugyldig vare specificeret for fjernelse.');
    }
    // Redirect back to the cart page without the query string
    redirect('cart.php');
    exit;
}

// --- Get Cart Items ---
$cartItems = $_SESSION['cart'] ?? [];
$totalPrice = 0.00;

// --- Define Navigation Items (Even if not highlighted, for layout consistency) ---
$accountNavItems = [
    'Billetter' => 'tickets.php',
    'Rediger data' => 'edit-data.php',
    'Transaktioner' => 'transactions.php',
    'Mine abonnementer' => 'subscriptions.php',
    'Skift adgangskode' => 'change-password.php',
    'Admin' => 'admin.php',
];

// Cart page is not typically part of account nav, so $currentPage won't match
$currentPage = basename($_SERVER['PHP_SELF']); // Will be 'cart.php'

?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indkøbskurv - AGF</title>
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
        /* No active style needed specifically for cart in account nav */
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include '../layout/navbar.php'; // Assumes navbar.php is in layout/ ?>

    <main class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">

            <!-- Optional: Account Navigation (Included for layout consistency as requested) -->
            <!-- You might choose to omit this nav block specifically for the cart -->
            <nav class="mb-6 pb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                    <?php foreach ($accountNavItems as $title => $file): ?>
                        <li class="mr-2">
                             <?php
                                // Cart page itself is not in the account items, so never active here
                                $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300';
                            ?>
                            <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>">
                                <?php echo htmlspecialchars($title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <!-- Cart Content -->
            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-6">Din Indkøbskurv</h2>

                <?php displayFlashMessages(); // Show messages (e.g., item removed) ?>

                <?php if (empty($cartItems)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Din indkøbskurv er tom.</p>
                        <a href="https://krisba.dk/ks/" class="mt-4 inline-block px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                            Se kommende kampe
                        </a>
                    </div>
                <?php else: ?>
                    <form action="process_order.php" method="POST">
                        <div class="flow-root">
                            <ul role="list" class="-my-6 divide-y divide-gray-200">
                                <?php foreach ($cartItems as $index => $item): ?>
                                    <?php
                                        // Basic assumption: item price is stored correctly
                                        $itemPrice = filter_var($item['price'] ?? 0, FILTER_VALIDATE_FLOAT);
                                        $totalPrice += $itemPrice;

                                        // --- Generate Hidden Fields for this item ---
                                        echo '<input type="hidden" name="items['.$index.'][type]" value="' . htmlspecialchars($item['type']) . '">';
                                        echo '<input type="hidden" name="items['.$index.'][price]" value="' . htmlspecialchars($item['price']) . '">';
                                        if ($item['type'] === 'ticket') {
                                            echo '<input type="hidden" name="items['.$index.'][match_id]" value="' . htmlspecialchars($item['match_id']) . '">';
                                            echo '<input type="hidden" name="items['.$index.'][ticket_type]" value="' . htmlspecialchars($item['ticket_type']) . '">';
                                            echo '<input type="hidden" name="items['.$index.'][section]" value="' . htmlspecialchars($item['section'] ?? '') . '">';
                                            echo '<input type="hidden" name="items['.$index.'][row]" value="' . htmlspecialchars($item['row'] ?? '') . '">';
                                            echo '<input type="hidden" name="items['.$index.'][seat_number]" value="' . htmlspecialchars($item['seat_number'] ?? '') . '">';
                                            // Display details (Assume opponent/date are stored in session for display)
                                            $displayTitle = htmlspecialchars($item['display_title'] ?? 'Billet');
                                            $displayDetails = 'Type: ' . htmlspecialchars($item['ticket_type']);
                                            if (!empty($item['section'])) {
                                                $displayDetails .= ' | Sektion: ' . htmlspecialchars($item['section']);
                                                if (!empty($item['row'])) $displayDetails .= ', Rk: ' . htmlspecialchars($item['row']);
                                                if (!empty($item['seat_number'])) $displayDetails .= ', Sæde: ' . htmlspecialchars($item['seat_number']);
                                            }
                                        } elseif ($item['type'] === 'subscription') {
                                            echo '<input type="hidden" name="items['.$index.'][subscription_type_id]" value="' . htmlspecialchars($item['subscription_type_id']) . '">';
                                            // Display details (Assume name is stored in session)
                                            $displayTitle = htmlspecialchars($item['display_title'] ?? 'Abonnement');
                                            $displayDetails = htmlspecialchars($item['display_description'] ?? '');
                                        } else {
                                             $displayTitle = 'Ukendt Vare';
                                             $displayDetails = '';
                                        }
                                    ?>
                                    <li class="flex py-6">
                                        <!-- Typically an image here, placeholder for now -->
                                        <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-100 flex items-center justify-center">
                                            <?php if($item['type'] === 'ticket'): ?>
                                                 <i class="fas fa-ticket-alt text-4xl text-gray-400"></i>
                                            <?php elseif($item['type'] === 'subscription'): ?>
                                                 <i class="fas fa-star text-4xl text-gray-400"></i>
                                            <?php endif; ?>

                                        </div>

                                        <div class="ml-4 flex flex-1 flex-col">
                                            <div>
                                                <div class="flex justify-between text-base font-medium text-gray-900">
                                                    <h3><?php echo $displayTitle; ?></h3>
                                                    <p class="ml-4"><?php echo number_format($itemPrice, 2, ',', '.'); ?> kr.</p>
                                                </div>
                                                <p class="mt-1 text-sm text-gray-500"><?php echo $displayDetails; ?></p>
                                            </div>
                                            <div class="flex flex-1 items-end justify-between text-sm">
                                                <p class="text-gray-500"><!-- Quantity could go here if needed --></p>
                                                <div class="flex">
                                                    <a href="cart.php?remove_item=<?php echo $index; ?>" type="button" class="font-medium text-red-600 hover:text-red-500">
                                                        <i class="fas fa-trash-alt mr-1"></i>Fjern
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div><!-- end flow-root -->

                        <div class="border-t border-gray-200 py-6 mt-6">
                            <div class="flex justify-between text-base font-medium text-gray-900">
                                <p>Subtotal</p>
                                <p><?php echo number_format($totalPrice, 2, ',', '.'); ?> kr.</p>
                            </div>
                            <p class="mt-0.5 text-sm text-gray-500">Forsendelse og moms beregnes ved kassen (hvis relevant).</p>
                            <div class="mt-6">
                                <!-- Hidden input for total price -->
                                <input type="hidden" name="total_price" value="<?php echo htmlspecialchars($totalPrice); ?>">
                                <button type="submit" class="w-full flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-blue-700">
                                    Gennemfør Ordre (Simuleret)
                                </button>
                            </div>
                            <div class="mt-6 flex justify-center text-center text-sm text-gray-500">
                                <p>
                                    eller
                                    <a href="https://krisba.dk/ks/" type="button" class="font-medium text-blue-600 hover:text-blue-500">
                                        Fortsæt med at handle
                                        <span aria-hidden="true"> →</span>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div><!-- end cart content -->
        </div>
    </main>

    <?php include '../layout/footer.php'; ?>

    <script>
      // --- Language Dropdown Logic (if needed on cart page) ---
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