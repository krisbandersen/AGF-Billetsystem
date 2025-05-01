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
$message = '';
$messageType = ''; // 'success' or 'error'
$currentUserData = []; // To store fetched user data

// --- Fetch Current User Data ---
try {
    // Select all relevant user data EXCEPT the password hash
    $sqlFetch = "SELECT user_id, first_name, last_name, email, phone_number, role, gender,
                        birth_date, street_name, house_number, floor, postal_code, city,
                        country, country_code, created_at, updated_at
                 FROM users WHERE user_id = :userid LIMIT 1";
    $userDataResult = executeQuery($sqlFetch, ['userid' => $userId]);

    if ($userDataResult && count($userDataResult) > 0) {
        $currentUserData = $userDataResult[0];
        // Format date for display if needed (e.g., if it comes as YYYY-MM-DD)
        // $currentUserData['birth_date_formatted'] = !empty($currentUserData['birth_date']) ? date('d-m-Y', strtotime($currentUserData['birth_date'])) : '';
    } else {
        // This shouldn't happen if session ID is valid, but handle defensively
        setFlashMessage('error', 'Kunne ikke finde brugerdata. Log venligst ind igen.');
        redirect('../logout.php'); // Force logout/login
        exit;
    }
} catch (Exception $e) {
    $message = 'Fejl ved hentning af brugerdata.';
    $messageType = 'error';
    // Avoid proceeding without current data
    $currentUserData = []; // Clear data to prevent form display issues
}


// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($currentUserData)) {
    // Collect submitted data (use current data as default if not submitted/empty)
    $firstName = trim($_POST['first_name'] ?? $currentUserData['first_name']);
    $lastName = trim($_POST['last_name'] ?? $currentUserData['last_name']);
    $email = trim($_POST['email'] ?? $currentUserData['email']);
    $phoneNumber = trim($_POST['phone_number'] ?? $currentUserData['phone_number']);
    $gender = trim($_POST['gender'] ?? $currentUserData['gender']);

    // Combine date parts - Assuming form submits day, month, year separately like register.php
    $birth_date_day = trim($_POST['birth_date_day'] ?? '');
    $birth_date_month = trim($_POST['birth_date_month'] ?? '');
    $birth_date_year = trim($_POST['birth_date_year'] ?? '');

    $birthDate = $currentUserData['birth_date']; // Keep original if nothing submitted

    if (!empty($birth_date_year) && !empty($birth_date_month) && !empty($birth_date_day)) {
         if (is_numeric($birth_date_year) && is_numeric($birth_date_month) && is_numeric($birth_date_day) &&
             checkdate((int)$birth_date_month, (int)$birth_date_day, (int)$birth_date_year)) {
            // Format as YYYY-MM-DD for database
            $birthDate = sprintf('%04d-%02d-%02d', $birth_date_year, $birth_date_month, $birth_date_day);
         } else {
             $message = 'Ugyldig fødselsdato angivet.';
             $messageType = 'error';
         }
    } elseif (!empty($birth_date_year) || !empty($birth_date_month) || !empty($birth_date_day)) {
         // If only parts are filled, it's an error
         $message = 'Udfyld venligst hele fødselsdatoen (DD, MM, YYYY) eller lad alle felter være tomme.';
         $messageType = 'error';
    } elseif (isset($_POST['birth_date_day'])) { // If fields were submitted but all empty
        $birthDate = null; // Allow clearing the date
    }


    $streetName = trim($_POST['street_name'] ?? $currentUserData['street_name']);
    $houseNumber = trim($_POST['house_number'] ?? $currentUserData['house_number']);
    $floor = trim($_POST['floor'] ?? $currentUserData['floor']);
    $postalCode = trim($_POST['postal_code'] ?? $currentUserData['postal_code']);
    $city = trim($_POST['city'] ?? $currentUserData['city']);
    $country = trim($_POST['country'] ?? $currentUserData['country']);
    $countryCode = trim($_POST['country_code'] ?? $currentUserData['country_code']);

    // --- Validation ---
    if (empty($message)) { // Proceed only if date validation passed
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $message = 'Fornavn, Efternavn og Email skal udfyldes.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Indtast venligst en gyldig email-adresse.';
            $messageType = 'error';
        } else {
            // --- Email Uniqueness Check (if email changed) ---
            $emailChanged = (strtolower($email) !== strtolower($currentUserData['email']));
            $emailConflict = false;
            if ($emailChanged) {
                try {
                    $sqlCheckEmail = "SELECT user_id FROM users WHERE email = :email AND user_id != :userid LIMIT 1";
                    $existingUser = executeQuery($sqlCheckEmail, ['email' => $email, 'userid' => $userId]);
                    if ($existingUser === false) {
                         throw new Exception("Database query failed during email check.");
                    }
                    if (!empty($existingUser)) {
                        $emailConflict = true;
                        $message = 'Denne email-adresse er allerede i brug af en anden konto.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                     $message = 'Fejl ved tjek af email. Prøv igen.';
                     $messageType = 'error';
                     $emailConflict = true; // Prevent update attempt
                }
            }

            // --- Proceed with Update if no validation errors or email conflict ---
            if (empty($message) && !$emailConflict) {
                try {
                    $sqlUpdate = "UPDATE users SET
                                    first_name = :firstname,
                                    last_name = :lastname,
                                    email = :email,
                                    phone_number = :phonenumber,
                                    gender = :gender,
                                    birth_date = :birthdate,
                                    street_name = :streetname,
                                    house_number = :housenumber,
                                    floor = :floor,
                                    postal_code = :postalcode,
                                    city = :city,
                                    country = :country,
                                    country_code = :countrycode
                                  WHERE user_id = :userid";

                    $params = [
                        'firstname' => $firstName,
                        'lastname' => $lastName,
                        'email' => $email,
                        'phonenumber' => $phoneNumber ?: null, // Store empty as NULL
                        'gender' => $gender ?: null,
                        'birthdate' => $birthDate, // Already formatted or NULL
                        'streetname' => $streetName ?: null,
                        'housenumber' => $houseNumber ?: null,
                        'floor' => $floor ?: null,
                        'postalcode' => $postalCode ?: null,
                        'city' => $city ?: null,
                        'country' => $country ?: null,
                        'countrycode' => $countryCode ?: null,
                        'userid' => $userId
                    ];

                    $updated = executeQuery($sqlUpdate, $params);

                    if ($updated) {
                        $message = 'Dine oplysninger er blevet opdateret!';
                        $messageType = 'success';

                        // --- Update Session Variables ---
                        $_SESSION['first_name'] = $firstName;
                        $_SESSION['last_name'] = $lastName;
                        $_SESSION['email'] = $email;
                        // Add others if you use them directly from session elsewhere

                        // --- Re-fetch Data to display the absolute latest ---
                        $userDataResult = executeQuery($sqlFetch, ['userid' => $userId]); // Re-use the fetch query
                        if ($userDataResult && count($userDataResult) > 0) {
                            $currentUserData = $userDataResult[0];
                        }

                    } else {
                        $message = 'Der opstod en uventet fejl under opdatering. Prøv igen senere.';
                        $messageType = 'error';
                         // Keep submitted data in form fields by updating $currentUserData with POST values
                         $currentUserData['first_name'] = $firstName;
                         $currentUserData['last_name'] = $lastName;
                         // etc. for all fields... (simpler: don't re-fetch on failure)
                    }

                } catch (PDOException $e) {
                    $message = 'Databasefejl under opdatering. Kontakt support.';
                    $messageType = 'error';
                } catch (Exception $e) {
                    $message = 'En generel fejl opstod. Prøv igen senere.';
                    $messageType = 'error';
                }
            }
        }
    }
     // If validation failed, update $currentUserData with submitted values to refill form correctly
     if ($messageType === 'error') {
         $currentUserData['first_name'] = $firstName;
         $currentUserData['last_name'] = $lastName;
         $currentUserData['email'] = $email;
         $currentUserData['phone_number'] = $phoneNumber;
         $currentUserData['gender'] = $gender;
         $currentUserData['birth_date'] = $birthDate; // Use the processed $birthDate
         $currentUserData['street_name'] = $streetName;
         $currentUserData['house_number'] = $houseNumber;
         $currentUserData['floor'] = $floor;
         $currentUserData['postal_code'] = $postalCode;
         $currentUserData['city'] = $city;
         $currentUserData['country'] = $country;
         $currentUserData['country_code'] = $countryCode;
         // Need to handle date parts separately for re-display
         list($currentUserData['birth_year_val'], $currentUserData['birth_month_val'], $currentUserData['birth_day_val']) = !empty($birthDate) ? explode('-', $birthDate) : ['', '', ''];
     }
}


// Prepare data for the form display (especially date parts)
list($birthYear, $birthMonth, $birthDay) = !empty($currentUserData['birth_date']) ? explode('-', $currentUserData['birth_date']) : ['', '', ''];
// Use the potentially updated values if validation failed
$displayBirthDay = $currentUserData['birth_day_val'] ?? $birthDay;
$displayBirthMonth = $currentUserData['birth_month_val'] ?? $birthMonth;
$displayBirthYear = $currentUserData['birth_year_val'] ?? $birthYear;


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
    <title data-lang-key="pageTitleEditData">AGF - Min Konto - Rediger Data</title>
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
         /* Add focus styles for better accessibility */
        input:focus, select:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            --tw-ring-inset: var(--tw-empty,/*!*/ /*!*/);
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: #2563eb; /* blue-500 */
            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
            border-color: #3b82f6; /* blue-500 */
        }
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

            <!-- Edit Data Section -->
            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-4" data-lang-key="editDataHeading">Rediger Mine Data</h2>

                <?php if (!empty($message)): ?>
                    <div class="mb-4 px-4 py-3 rounded relative
                        <?php echo ($messageType === 'success') ? 'bg-green-100 border border-green-400 text-green-700' : ''; ?>
                        <?php echo ($messageType === 'error') ? 'bg-red-100 border border-red-400 text-red-700' : ''; ?>"
                         role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (empty($currentUserData) && empty($message)): // Show loading or specific error if data fetch failed initially ?>
                     <p class="text-gray-600">Indlæser brugerdata...</p>
                <?php elseif (!empty($currentUserData)): ?>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6 max-w-2xl">

                        <!-- Personal Information -->
                         <h3 class="text-lg font-medium leading-6 text-gray-900 border-b pb-2 mb-4">Personlige Oplysninger</h3>
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                             <div class="sm:col-span-3">
                                <label for="first_name" class="block text-sm font-medium text-gray-700">Fornavn <span class="text-red-500">*</span></label>
                                <input type="text" id="first_name" name="first_name" required autocomplete="given-name"
                                       value="<?php echo htmlspecialchars($currentUserData['first_name'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                             <div class="sm:col-span-3">
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Efternavn <span class="text-red-500">*</span></label>
                                <input type="text" id="last_name" name="last_name" required autocomplete="family-name"
                                       value="<?php echo htmlspecialchars($currentUserData['last_name'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                             <div class="sm:col-span-4">
                                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" required autocomplete="email"
                                       value="<?php echo htmlspecialchars($currentUserData['email'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                             <div class="sm:col-span-2">
                                <label for="gender" class="block text-sm font-medium text-gray-700">Køn</label>
                                <select id="gender" name="gender" autocomplete="sex"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm">
                                    <option value="" <?php echo empty($currentUserData['gender']) ? 'selected' : ''; ?>>Vælg...</option>
                                    <option value="m" <?php echo ($currentUserData['gender'] ?? '') === 'm' ? 'selected' : ''; ?>>Mand</option>
                                    <option value="k" <?php echo ($currentUserData['gender'] ?? '') === 'k' ? 'selected' : ''; ?>>Kvinde</option>
                                    <option value="a" <?php echo ($currentUserData['gender'] ?? '') === 'a' ? 'selected' : ''; ?>>Andet</option>
                                </select>
                            </div>

                             <div class="sm:col-span-6">
                                <label class="block text-sm font-medium text-gray-700">Fødselsdato</label>
                                <div class="mt-1 grid grid-cols-3 gap-2">
                                    <div>
                                        <label for="birth_date_day" class="sr-only">Dag</label>
                                        <input type="number" id="birth_date_day" name="birth_date_day" placeholder="DD" min="1" max="31"
                                               value="<?php echo htmlspecialchars($displayBirthDay); ?>"
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="birth_date_month" class="sr-only">Måned</label>
                                        <input type="number" id="birth_date_month" name="birth_date_month" placeholder="MM" min="1" max="12"
                                                value="<?php echo htmlspecialchars($displayBirthMonth); ?>"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="birth_date_year" class="sr-only">År</label>
                                        <input type="number" id="birth_date_year" name="birth_date_year" placeholder="YYYY" min="1900" max="<?php echo date('Y'); ?>"
                                                value="<?php echo htmlspecialchars($displayBirthYear); ?>"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                                    </div>
                                </div>
                             </div>
                        </div>

                        <!-- Contact Information -->
                         <h3 class="text-lg font-medium leading-6 text-gray-900 border-b pt-6 pb-2 mb-4">Kontakt Information</h3>
                         <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                             <div class="sm:col-span-2">
                                <label for="country_code" class="block text-sm font-medium text-gray-700">Landekode</label>
                                <select id="country_code" name="country_code"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none sm:text-sm">
                                    <option value="+45" <?php echo ($currentUserData['country_code'] ?? '+45') === '+45' ? 'selected' : ''; ?>>+45 (Danmark)</option>
                                    <option value="+46" <?php echo ($currentUserData['country_code'] ?? '') === '+46' ? 'selected' : ''; ?>>+46 (Sverige)</option>
                                    <option value="+47" <?php echo ($currentUserData['country_code'] ?? '') === '+47' ? 'selected' : ''; ?>>+47 (Norge)</option>
                                    <option value="+49" <?php echo ($currentUserData['country_code'] ?? '') === '+49' ? 'selected' : ''; ?>>+49 (Tyskland)</option>
                                     <!-- Add more countries as needed -->
                                </select>
                             </div>
                             <div class="sm:col-span-4">
                                <label for="phone_number" class="block text-sm font-medium text-gray-700">Telefonnummer</label>
                                <input type="tel" id="phone_number" name="phone_number" autocomplete="tel"
                                       value="<?php echo htmlspecialchars($currentUserData['phone_number'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>
                         </div>

                         <!-- Address Information -->
                         <h3 class="text-lg font-medium leading-6 text-gray-900 border-b pt-6 pb-2 mb-4">Adresse (Valgfri)</h3>
                         <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                             <div class="sm:col-span-6">
                                <label for="street_name" class="block text-sm font-medium text-gray-700">Vejnavn</label>
                                <input type="text" id="street_name" name="street_name" autocomplete="street-address"
                                       value="<?php echo htmlspecialchars($currentUserData['street_name'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                              <div class="sm:col-span-2">
                                <label for="house_number" class="block text-sm font-medium text-gray-700">Husnr.</label>
                                <input type="text" id="house_number" name="house_number"
                                       value="<?php echo htmlspecialchars($currentUserData['house_number'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                              <div class="sm:col-span-2">
                                <label for="floor" class="block text-sm font-medium text-gray-700">Etage m.m.</label>
                                <input type="text" id="floor" name="floor"
                                       value="<?php echo htmlspecialchars($currentUserData['floor'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                             <div class="sm:col-span-2">
                                <label for="postal_code" class="block text-sm font-medium text-gray-700">Postnummer</label>
                                <input type="text" id="postal_code" name="postal_code" autocomplete="postal-code"
                                       value="<?php echo htmlspecialchars($currentUserData['postal_code'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                             <div class="sm:col-span-3">
                                <label for="city" class="block text-sm font-medium text-gray-700">By</label>
                                <input type="text" id="city" name="city" autocomplete="address-level2"
                                       value="<?php echo htmlspecialchars($currentUserData['city'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>

                              <div class="sm:col-span-3">
                                <label for="country" class="block text-sm font-medium text-gray-700">Land</label>
                                <input type="text" id="country" name="country" autocomplete="country-name"
                                       value="<?php echo htmlspecialchars($currentUserData['country'] ?? ''); ?>"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm sm:text-sm">
                             </div>
                         </div>

                        <!-- Submit Button -->
                        <div class="pt-5">
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        data-lang-key="saveChangesBtn">
                                    Gem Ændringer
                                </button>
                            </div>
                        </div>
                    </form>
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
    </script>
</body>
</html>