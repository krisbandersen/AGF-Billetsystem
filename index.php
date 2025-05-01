<?php
session_start();
require_once 'php/utils.php';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-lang-key="pageTitle">AGF - Kommende Kampe</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  	<script src="https://cdn.jsdelivr.net/npm/vanilla-lazyload@19.1.3/dist/lazyload.min.js"></script>
  <style>
    #language-menu {
      transition: opacity 0.2s ease-out, transform 0.2s ease-out;
    }
    #language-menu.hidden {
      opacity: 0;
      transform: translateY(-10px);
      pointer-events: none;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 bg-[url(https://robostaticcontent.s3.amazonaws.com/Content/AGF/Images/bg_TestServ.jpg)] bg-cover bg-center bg-no-repeat">

    <?php include 'layout/navbar.php'; ?>

    <main class="container mx-auto my-8 py-8 px-4">
  <?php
    // --- Fetch Upcoming Matches --- (Code remains the same)
    $upcomingMatches = [];
    $matchesFetchError = null;
    try {
        $sqlMatches = "SELECT match_id, match_datetime, opponent, stadium, card_url
                       FROM matches
                       WHERE match_datetime >= CURDATE()
                       ORDER BY match_datetime ASC
                       LIMIT 3";
        $upcomingMatches = executeQuery($sqlMatches);
        if ($upcomingMatches === false) {
             $matchesFetchError = "Database query failed for matches."; $upcomingMatches = [];
        }
    } catch (Exception $e) {
        $matchesFetchError = "An exception occurred while fetching matches.";
        error_log("Error fetching upcoming matches: " . $e->getMessage());
        $upcomingMatches = [];
    }

    // --- Fetch Available Subscriptions (MODIFIED SQL) ---
    $availableSubscriptions = [];
    $subsFetchError = null;
    try {
        // Select subscriptions and include the new card_url
        $sqlSubs = "SELECT subscription_type_id, name, description, price, billing_interval, card_url -- Added card_url
                    FROM subscription_types
                    WHERE price IS NOT NULL AND price > 0
                       OR name LIKE '%Sæsonkort%'
                       OR name LIKE '%AGF+%'
                    ORDER BY FIELD(name, 'Sæsonkort Nedre C 24/25', 'AGF+ Medlemskab'), price DESC, name ASC
                    LIMIT 3";
        $availableSubscriptions = executeQuery($sqlSubs);
        if ($availableSubscriptions === false) {
             $subsFetchError = "Database query failed for subscriptions."; $availableSubscriptions = [];
        }
    } catch (Exception $e) {
        $subsFetchError = "An exception occurred while fetching subscriptions.";
        error_log("Error fetching subscriptions: " . $e->getMessage());
        $availableSubscriptions = [];
    }
    ?>

    <?php // Display fetch errors (Code remains the same) ?>
    <?php if ($matchesFetchError): ?>
         <p class='text-red-500 text-center bg-white p-4 rounded shadow-md mb-4'>Fejl ved hentning af kampe.</p>
    <?php endif; ?>
     <?php if ($subsFetchError): ?>
         <p class='text-red-500 text-center bg-white p-4 rounded shadow-md mb-4'>Fejl ved hentning af abonnementer.</p>
    <?php endif; ?>

     <?php displayFlashMessages(); ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <?php if (!empty($upcomingMatches)): ?>
            <?php foreach ($upcomingMatches as $match): ?>
                <?php 
                    $matchId = sanitizeOutput($match['match_id']);
                    $opponent = sanitizeOutput($match['opponent']);
                    $stadium = sanitizeOutput($match['stadium']);
                    $imageUrl = filter_var($match['card_url'], FILTER_SANITIZE_URL) ?: 'assets/default-match.jpeg';
                    $formattedDateTime = 'Ukendt dato';
                    if (!empty($match['match_datetime'])) { try { $dateTime = new DateTime($match['match_datetime']); $formattedDateTime = $dateTime->format('d/m/Y H:i'); } catch (Exception $e) { /* log */ } }
                    $isHomeGame = (stripos($stadium, 'Ceres Park') !== false);
                    $matchTitle = $isHomeGame ? "AGF - " . $opponent : $opponent . " - AGF";
                    $matchAltText = sanitizeOutput($matchTitle . " kamp");
                    $matchTicketPrice = 150.00;
                ?>
                <div class="flex flex-col bg-white rounded-lg overflow-hidden shadow-lg match-card transition duration-300 ease-in-out hover:-translate-y-1 hover:shadow-xl group" data-match-id="<?php echo $matchId; ?>">
                    <div class="relative h-48 w-full overflow-hidden">
                        <img src="<?php echo $imageUrl; ?>" alt="<?php echo $matchAltText; ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy"/>
                    </div>
                    <div class="p-4 flex flex-col flex-grow">
                         <h3 class="text-lg font-semibold truncate mb-1" title="<?php echo $matchTitle; ?>"><?php echo $matchTitle; ?></h3>
                         <p class="text-sm text-gray-600 mb-2">
                              <i class="fas fa-calendar-alt fa-fw mr-1 opacity-75"></i> <?php echo $formattedDateTime; ?>
                              <br>
                              <i class="fas fa-map-marker-alt fa-fw mr-1 opacity-75"></i> <?php echo $stadium; ?>
                         </p>
                         <p class="text-sm text-gray-800 font-medium mb-3">Fra <?php echo number_format($matchTicketPrice, 2, ',', '.'); ?> kr.</p>
                         <div class="mt-auto flex justify-between items-center pt-2 border-t border-gray-100">
                                <a href="match-details.php?id=<?php echo $matchId; ?>" target="_blank" class="laes-mere-btn text-blue-600 uppercase text-xs font-bold hover:text-blue-800 tracking-wider" data-lang-key="readMore">LÆS MERE <i class="fas fa-arrow-right ml-1"></i></a>
                                <a href="account/stadium.php?matchid=<?php echo $matchId; ?>" class="rounded-full bg-gray-900 p-3 text-white hover:bg-gray-700 transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-gray-900" aria-label="Læg billet i kurv" data-lang-key="cartLabel" data-lang-attr="aria-label" title="Læg billet i kurv">
                                    <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                </a>
                         </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>


        <?php // --- Loop through and display subscriptions --- ?>
         <?php if (!empty($availableSubscriptions)): ?>
            <?php foreach ($availableSubscriptions as $sub): ?>
                 <?php
                    // Data preparation for subscription card
                    $subTypeId = sanitizeOutput($sub['subscription_type_id']);
                    $subName = sanitizeOutput($sub['name']);
                    $subDesc = sanitizeOutput($sub['description'] ?? '');
                    $subPrice = filter_var($sub['price'] ?? 0, FILTER_VALIDATE_FLOAT);
                    $subInterval = $sub['billing_interval'];
                    $priceDisplay = number_format($subPrice, 2, ',', '.');
                    if ($subInterval === 'monthly') $priceDisplay .= ' kr./md.';
                    elseif ($subInterval === 'annually') $priceDisplay .= ' kr./år';
                    else $priceDisplay .= ' kr.';
                    $subAltText = $subName . " abonnement";

                    // === IMAGE LOGIC START (MODIFIED) ===
                    // Get the card_url from the fetched data, sanitize it
                    $subImageUrl = !empty($sub['card_url']) ? filter_var($sub['card_url'], FILTER_SANITIZE_URL) : null;
                    // === IMAGE LOGIC END ===

                 ?>
                 <!-- Dynamic Subscription Card -->
                 <div class="flex flex-col bg-white rounded-lg overflow-hidden shadow-lg sub-card transition duration-300 ease-in-out hover:-translate-y-1 hover:shadow-xl group" data-sub-id="<?php echo $subTypeId; ?>">
                     <!-- MODIFIED IMAGE AREA - Uses fetched URL or fallback -->
                     <div class="relative h-48 w-full overflow-hidden <?php echo !$subImageUrl ? 'bg-gradient-to-br from-blue-100 to-indigo-200 flex items-center justify-center' : ''; ?>">
                         <?php if ($subImageUrl): ?>
                             <img src="<?php echo $subImageUrl; ?>" alt="<?php echo $subAltText; ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy"/>
                         <?php else: ?>
                             <!-- Fallback Icon -->
                             <i class="fas fa-star text-6xl text-indigo-400 opacity-80"></i>
                         <?php endif; ?>
                    </div>
                    <!-- END MODIFIED IMAGE AREA -->
                     <div class="p-4 flex flex-col flex-grow">
                         <h3 class="text-lg font-semibold truncate mb-1" title="<?php echo $subName; ?>"><?php echo $subName; ?></h3>
                         <?php if (!empty($subDesc)): ?>
                             <p class="text-sm text-gray-600 mb-2 text-ellipsis overflow-hidden h-10">
                                 <?php echo $subDesc; ?>
                             </p>
                         <?php endif; ?>
                         <p class="text-lg text-gray-800 font-semibold mb-3"><?php echo $priceDisplay; ?></p>
                         <div class="mt-auto flex justify-between items-center pt-2 border-t border-gray-100">
                             <a href="subscription-details.php?id=<?php echo $subTypeId; ?>" target="_blank" class="laes-mere-btn text-blue-600 uppercase text-xs font-bold hover:text-blue-800 tracking-wider" data-lang-key="readMoreSub">LÆS MERE <i class="fas fa-arrow-right ml-1"></i></a>
                             <form action="account/add_to_cart.php" method="POST" class="inline">
                                 <input type="hidden" name="item_type" value="subscription">
                                 <input type="hidden" name="subscription_type_id" value="<?php echo $subTypeId; ?>">
                                 <input type="hidden" name="price" value="<?php echo $subPrice; ?>">
                                 <input type="hidden" name="display_title" value="<?php echo $subName; ?>">
                                 <input type="hidden" name="display_description" value="<?php echo $subDesc; ?>">
                                 <button type="submit" class="rounded-full bg-gray-900 p-3 text-white hover:bg-gray-700 transition duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-gray-900" aria-label="Læg abonnement i kurv" data-lang-key="cartLabelSub" data-lang-attr="aria-label" title="Læg abonnement i kurv">
                                     <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                 </button>
                             </form>
                         </div>
                     </div>
                 </div> <!-- End Subscription Card -->
            <?php endforeach; ?>
        <?php endif; // End subscription loop ?>


        <?php // --- Display message if no items found at all --- (Code remains the same) ?>
        <?php if (empty($upcomingMatches) && empty($availableSubscriptions) && !$matchesFetchError && !$subsFetchError): ?>
            <p class="text-center text-gray-500 md:col-span-2 lg:col-span-3 bg-white p-6 rounded shadow-md" data-lang-key="noItemsFound">
                Der er ingen kommende kampe eller abonnementer tilgængelige i øjeblikket.
            </p>
        <?php endif; ?>

    </div> <!-- End Grid -->
  </main>

  <?php include 'layout/footer.php'; ?>

  <script>
    // --- Translations Object (keep as is or update as needed) ---
    const translations = {
         da: {
            pageTitle: "AGF - Kommende Kampe",
            navTickets: "Billetter",
            navLogin: "LOG IND",
            readMore: "LÆS MERE",
            cartLabel: "Køb billet",
            closeDetailsLabel: "Luk detaljer",
            errorFetchingMatches: "Der opstod en fejl under hentning af kampe. Prøv venligst igen senere.",
            noUpcomingMatches: "Der er ingen kommende kampe planlagt i øjeblikket.",
            // Add more generic keys if needed
        },
        en: {
            pageTitle: "AGF - Upcoming Matches",
            navTickets: "Tickets",
            navLogin: "LOG IN",
            readMore: "READ MORE",
            cartLabel: "Buy Ticket",
            closeDetailsLabel: "Close details",
            errorFetchingMatches: "An error occurred while fetching matches. Please try again later.",
            noUpcomingMatches: "There are no upcoming matches scheduled at the moment.",
        },
        de: {
             pageTitle: "AGF - Kommende Spiele",
             navTickets: "Tickets",
             navLogin: "ANMELDEN",
             readMore: "MEHR LESEN",
             cartLabel: "Ticket kaufen",
             closeDetailsLabel: "Details schließen",
             errorFetchingMatches: "Beim Abrufen der Spiele ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.",
             noUpcomingMatches: "Momentan sind keine bevorstehenden Spiele geplant.",
        }
        // Add other languages if needed
    };

    // --- Update Translations Function (keep as is) ---
    function updateTranslations(langCode) {
        const code = langCode || document.documentElement.lang || 'da';
        if (!translations[code]) {
            console.warn(`Language code "${code}" not found. Defaulting to 'da'.`);
            langCode = 'da';
        }
        document.documentElement.lang = code;
        document.querySelectorAll('[data-lang-key]').forEach(element => {
            const key = element.dataset.langKey;
            const translation = translations[code]?.[key] ?? translations['da']?.[key]; // Fallback lookup
            if (translation !== undefined) {
                const attribute = element.dataset.langAttr;
                if (attribute) {
                    element.setAttribute(attribute, translation);
                     if (element.tagName === 'TITLE' && attribute === 'title') { document.title = translation; }
                     if (element.tagName === 'BUTTON' && attribute === 'aria-label') { element.setAttribute('title', translation); } // Update title for buttons
                } else {
                    element.textContent = translation;
                }
            } else {
                console.warn(`Translation key "${key}" not found for language "${code}" or fallback 'da'.`);
            }
        });
    }

    // --- Language Dropdown Logic (keep as is) ---
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
                updateTranslations(selectedLangCode); // Trigger update
                langMenu.classList.add('hidden');
                langButton.setAttribute('aria-expanded', 'false');
                langOptions.forEach(opt => opt.classList.remove('bg-gray-100', 'font-semibold'));
                option.classList.add('bg-gray-100', 'font-semibold');
                 // Optional: Store preference in localStorage
                 // localStorage.setItem('preferredLanguage', selectedLangCode);
            });
        });
    } else {
         console.warn("Language dropdown elements not found.");
    }


    // --- Initial Load ---
    // Optional: Check localStorage for preferred language
    // const preferredLang = localStorage.getItem('preferredLanguage');
    const initialLang = /* preferredLang || */ document.documentElement.lang || 'da';
    updateTranslations(initialLang);

    // Update dropdown button text and highlight selected option initially
     if (currentLangSpan) {
        const initialLangOption = document.querySelector(`.language-option[data-lang="${initialLang}"]`);
        if (initialLangOption) {
            currentLangSpan.textContent = initialLangOption.textContent;
            langOptions.forEach(opt => opt.classList.remove('bg-gray-100', 'font-semibold'));
            initialLangOption.classList.add('bg-gray-100', 'font-semibold');
        }
     }

 </script>
</body>
</html>