<?php
$cartItemCount = 0; // Default to 0
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartItemCount = count($_SESSION['cart']);
}
?>
<nav class="bg-[#1f2237] text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Increased vertical padding slightly for more breathing room if logo/text are larger -->
      <div class="flex justify-between items-center py-5">

        <!-- Logo and Title Section -->
        <!-- Increased space between logo and text to space-x-6 -->
        <div class="flex items-center space-x-6"> 

          <!-- Logo: Kept h-32, adjusted top slightly to better align with larger text -->
           <a href="https://krisba.dk/ks"><img src="https://krisba.dk/ks/assets/agf-logo.png"
               alt="AGF Logo"
               class="relative h-32 top-6 -mb-4" 
               data-lang-key="logoAlt"
               data-lang-attr="alt"></a>

          <!-- Title Text: Increased size to text-3xl, changed weight to semibold -->
          <span class="text-3xl font-semibold"
                data-lang-key="navTickets">
                AGF Billetsystem
          </span>
        </div>

        <!-- Right Navigation Items -->
        <ul class="flex space-x-6 items-center">
          <li class="relative">
            <button id="language-button" type="button" class="text-white flex items-center hover:text-gray-300 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                <span id="current-language">Dansk</span>
                <i class="fas fa-chevron-down ml-2 text-xs"></i>
            </button>

            <div id="language-menu"
                 class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20"
                 role="menu" aria-orientation="vertical" aria-labelledby="language-button">
                <div class="py-1" role="none">
                    <a href="#" class="language-option text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" data-lang="da">Dansk</a>
                    <a href="#" class="language-option text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" data-lang="en">English</a>
                    <a href="#" class="language-option text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem" data-lang="de">German</a>
                </div>
            </div>
          </li>
          <li class="relative"> <!-- Add relative positioning for the dropdown -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                    // Prepare user name safely
                    $userNameDisplay = htmlspecialchars(($_SESSION["first_name"] ?? '') . ' ' . ($_SESSION["last_name"] ?? ''));
                ?>
                <!-- Dropdown Trigger Button (User Name) -->
                <button id="user-menu-button" type="button" class="text-white flex items-center hover:text-gray-300 focus:outline-none" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-user mr-2 hidden sm:inline"></i><!-- Optional user icon -->
                    <span class="text-lg font-medium">
                       <?php echo $userNameDisplay; ?>
                    </span>
                    <i class="fas fa-chevron-down ml-2 text-xs"></i> <!-- Dropdown arrow -->
                </button>

                <!-- User Dropdown Panel -->
                <div id="user-menu"
                     class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20"
                     role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button">
                    <div class="py-1" role="none">
                        <!-- Account Link (Added for convenience) -->
                        <a href="https://krisba.dk/ks/account/tickets.php" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem">
                          <i class="fas fa-user-circle fa-fw mr-2"></i> Min Konto
                        </a>
                        <!-- Cart Link -->
                        <a href="https://krisba.dk/ks/account/cart.php" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100 relative" role="menuitem">
                           <i class="fas fa-shopping-cart fa-fw mr-2"></i> Kurv
                           <?php if ($cartItemCount > 0): ?>
                               <!-- Cart Count Badge (Optional, can be subtle here) -->
                               <span class="absolute top-1 right-2 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                                   <?php echo $cartItemCount; ?>
                               </span>
                           <?php endif; ?>
                        </a>
                        <!-- Logout Link -->
                        <a href="https://krisba.dk/ks/logout.php" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem">
                            <i class="fas fa-sign-out-alt fa-fw mr-2"></i> Log ud
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <a href="https://krisba.dk/ks/login.php" class="text-white flex items-center hover:text-gray-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    <span data-lang-key="navLogin" class="text-lg font-medium">LOG IND</span>
                </a>
            <?php endif; ?>
          </li>
        </ul>
      </div>
    </div>
  <script>
      // --- User Dropdown Logic ---
      const userMenuButton = document.getElementById('user-menu-button');
      const userMenu = document.getElementById('user-menu');

      if (userMenuButton && userMenu) {
          userMenuButton.addEventListener('click', (event) => {
              event.stopPropagation(); // Prevent document click listener from closing immediately
              const isExpanded = userMenuButton.getAttribute('aria-expanded') === 'true';
              userMenu.classList.toggle('hidden');
              userMenuButton.setAttribute('aria-expanded', String(!isExpanded));
              // Hide language menu if open
              if (langMenu && !langMenu.classList.contains('hidden')) {
                  langMenu.classList.add('hidden');
                  if (langButton) langButton.setAttribute('aria-expanded', 'false');
              }
          });
      }

      // --- Close Dropdowns on Outside Click ---
      document.addEventListener('click', (event) => {
          // Close Language Menu
          if (langMenu && !langMenu.classList.contains('hidden')) {
                if (!langButton.contains(event.target) && !langMenu.contains(event.target)) {
                    langMenu.classList.add('hidden');
                    langButton.setAttribute('aria-expanded', 'false');
                }
          }
          // Close User Menu
          if (userMenu && !userMenu.classList.contains('hidden')) {
                if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                    userMenu.classList.add('hidden');
                    userMenuButton.setAttribute('aria-expanded', 'false');
                }
          }
      });
  </script>
  </nav>