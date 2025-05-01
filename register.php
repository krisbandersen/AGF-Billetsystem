<?php
session_start(); // Start the session at the very beginning
require_once 'php/utils.php'; // Include your utility functions

// --- MODIFIED SESSION CHECK ---
if (isset($_SESSION['user_id'])) { // Changed from BrugerID
    redirect('account/tickets.php');
}
// --- END MODIFICATION ---

$error_message = ''; // Initialize error message variable
// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data (using new expected names where applicable)
    $first_name = trim($_POST['fornavn'] ?? ''); // Keep form names, map to new vars
    $last_name = trim($_POST['efternavn'] ?? '');
    $gender = trim($_POST['koen'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date_day = trim($_POST['foedselsdag'] ?? '');
    $birth_date_month = trim($_POST['foedselsmaaned'] ?? '');
    $birth_date_year = trim($_POST['foedselsaar'] ?? '');

    // Combine date parts if all are provided and numeric
    $birth_date = null;
    if (!empty($birth_date_year) && !empty($birth_date_month) && !empty($birth_date_day) &&
        is_numeric($birth_date_year) && is_numeric($birth_date_month) && is_numeric($birth_date_day)) {
        // Format as YYYY-MM-DD
        $birth_date = sprintf('%04d-%02d-%02d', $birth_date_year, $birth_date_month, $birth_date_day);
    } elseif (!empty($birth_date_year) || !empty($birth_date_month) || !empty($birth_date_day)) {
        // If some parts are filled but not all, consider it an error or incomplete
        $error_message = "Udfyld venligst hele fødselsdatoen (DD, MM, YYYY).";
    } // If all are empty, $birth_date remains null, which is acceptable

    $street_name = trim($_POST['vejnavn'] ?? '');
    $house_number = trim($_POST['husnr'] ?? '');
    $floor = trim($_POST['etage'] ?? '');
    $postal_code = trim($_POST['postnummer'] ?? '');
    $city = trim($_POST['by'] ?? '');
    $country = trim($_POST['land'] ?? '');
    $country_code = trim($_POST['landekode'] ?? '');
    $phone_number = trim($_POST['telefonnummer'] ?? '');
    $password = $_POST['kodeord'] ?? '';
    $confirm_password = $_POST['bekraeft_kodeord'] ?? '';

    // Validate form data (only if no previous error like incomplete date)
    if (empty($error_message)) {
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error_message = "Fornavn, Efternavn, Email og Kodeord skal udfyldes.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Kodeordene stemmer ikke overens.";
        } elseif (empty($_POST['accept_terms'])) {
            $error_message = "Du skal acceptere privatlivspolitikken og vilkårene.";
        } else {
            // --- MODIFIED CALL TO registerUser ---
            // Call registration function with new variable names matching function signature
            $userId = registerUser(
                $first_name, $last_name, $gender, $email, $birth_date, // Use combined $birth_date
                $street_name, $house_number, $floor, $postal_code, $city, $country,
                $country_code, $phone_number, $password
            );
            // --- END MODIFICATION ---

            if ($userId) {
                // Registration successful - Use typed flash message
                setFlashMessage('success', 'Din konto er blevet oprettet. Du kan nu logge ind.');
                redirect('login.php');
            } else {
  
                if (empty($_SESSION['flash_messages'])) {
                     $error_message = "Der opstod en ukendt fejl under registreringen. Prøv venligst igen.";
                     logEvent('ERROR', "User registration failed for " . $email . " - unknown error in register.php.");
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opret konto - AGF</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* Add specific styles if needed */
    </style>
</head>
<body class="font-sans bg-[url(https://robostaticcontent.s3.amazonaws.com/Content/AGF/Images/bg_TestServ.jpg)] bg-cover bg-center bg-no-repeat">
    <div class="min-h-screen flex flex-col items-center justify-center pt-12 pb-8 px-4 sm:px-6 lg:px-8">
        <!-- Logos -->
        <div class="flex items-center space-x-4 mb-10">
            <img src="assets/agf-logo.png" alt="AGF Logo" class="h-24 sm:h-32">
        </div>
        <!-- Registration Box -->
        <div class="bg-white p-6 sm:p-8 rounded-sm shadow-lg w-full max-w-2xl">
            <div class="flex flex-col sm:flex-row justify-between items-baseline mb-6 gap-2">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Opret konto</h2>
                <div class="text-sm whitespace-nowrap">
                    <span>eller </span>
                    <button onclick="window.location.href='login.php'"
                            class="font-bold hover:text-gray-600 border-b-2 border-black hover:border-gray-900 pb-0.5 focus:outline-none cursor-pointer">
                        log ind
                    </button>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo sanitizeOutput($error_message); ?></span>
                </div>
            <?php endif; ?>
            <?php displayFlashMessages(); // Display flash messages set by registerUser or earlier ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
                <!-- Personal Information -->
                <div class="mb-4">
                    <label for="fornavn" class="block text-sm font-medium text-gray-700 mb-1">Fornavn <span class="text-red-500">*</span></label>
                    <input type="text" id="fornavn" name="fornavn" required
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['fornavn']) ? sanitizeOutput($_POST['fornavn']) : ''; ?>">
                </div>

                <div class="mb-4">
                    <label for="efternavn" class="block text-sm font-medium text-gray-700 mb-1">Efternavn <span class="text-red-500">*</span></label>
                    <input type="text" id="efternavn" name="efternavn" required
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['efternavn']) ? sanitizeOutput($_POST['efternavn']) : ''; ?>">
                </div>

                <div class="mb-4">
                    <label for="koen" class="block text-sm font-medium text-gray-700 mb-1">Køn</label>
                    <select id="koen" name="koen"
                            class="w-full border border-gray-300 p-3 rounded-none appearance-none bg-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="" <?php echo (!isset($_POST['koen']) || $_POST['koen'] == '') ? 'selected' : ''; ?>>Vælg...</option>
                        <option value="m" <?php echo (isset($_POST['koen']) && $_POST['koen'] == 'm') ? 'selected' : ''; ?>>Mand</option>
                        <option value="k" <?php echo (isset($_POST['koen']) && $_POST['koen'] == 'k') ? 'selected' : ''; ?>>Kvinde</option>
                        <option value="a" <?php echo (isset($_POST['koen']) && $_POST['koen'] == 'a') ? 'selected' : ''; ?>>Andet</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['email']) ? sanitizeOutput($_POST['email']) : ''; ?>">
                </div>

                <!-- Birth Date -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fødselsdato</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label for="foedselsdag" class="sr-only">Dag</label>
                            <input type="number" id="foedselsdag" name="foedselsdag" placeholder="DD" min="1" max="31"
                                class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo isset($_POST['foedselsdag']) ? sanitizeOutput($_POST['foedselsdag']) : ''; ?>">
                        </div>
                        <div>
                             <label for="foedselsmaaned" class="sr-only">Måned</label>
                            <input type="number" id="foedselsmaaned" name="foedselsmaaned" placeholder="MM" min="1" max="12"
                                class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo isset($_POST['foedselsmaaned']) ? sanitizeOutput($_POST['foedselsmaaned']) : ''; ?>">
                        </div>
                        <div>
                             <label for="foedselsaar" class="sr-only">År</label>
                            <input type="number" id="foedselsaar" name="foedselsaar" placeholder="YYYY" min="1900" max="<?php echo date('Y'); ?>"
                                class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                                value="<?php echo isset($_POST['foedselsaar']) ? sanitizeOutput($_POST['foedselsaar']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                 <hr class="my-6 border-gray-300">
                 <p class="text-sm text-gray-600 mb-4">Adresseoplysninger (valgfrit)</p>

                <div class="mb-4">
                    <label for="vejnavn" class="block text-sm font-medium text-gray-700 mb-1">Vejnavn</label>
                    <input type="text" id="vejnavn" name="vejnavn"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['vejnavn']) ? sanitizeOutput($_POST['vejnavn']) : ''; ?>">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="husnr" class="block text-sm font-medium text-gray-700 mb-1">Husnr.</label>
                        <input type="text" id="husnr" name="husnr"
                            class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                            value="<?php echo isset($_POST['husnr']) ? sanitizeOutput($_POST['husnr']) : ''; ?>">
                    </div>
                    <div>
                        <label for="etage" class="block text-sm font-medium text-gray-700 mb-1">Etage m.m.</label>
                        <input type="text" id="etage" name="etage"
                            class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                            value="<?php echo isset($_POST['etage']) ? sanitizeOutput($_POST['etage']) : ''; ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="postnummer" class="block text-sm font-medium text-gray-700 mb-1">Postnummer</label>
                    <input type="text" id="postnummer" name="postnummer"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['postnummer']) ? sanitizeOutput($_POST['postnummer']) : ''; ?>">
                </div>

                <div class="mb-4">
                    <label for="by" class="block text-sm font-medium text-gray-700 mb-1">By</label>
                    <input type="text" id="by" name="by"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['by']) ? sanitizeOutput($_POST['by']) : ''; ?>">
                </div>

                <div class="mb-4">
                    <label for="land" class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                    <input type="text" id="land" name="land"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                        value="<?php echo isset($_POST['land']) ? sanitizeOutput($_POST['land']) : ''; ?>">
                </div>

                <!-- Phone Information -->
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="col-span-1">
                        <label for="landekode" class="block text-sm font-medium text-gray-700 mb-1">Landekode</label>
                        <select id="landekode" name="landekode"
                                class="w-full border border-gray-300 p-3 rounded-none appearance-none bg-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="+45" <?php echo (!isset($_POST['landekode']) || $_POST['landekode'] == '+45') ? 'selected' : ''; ?>>+45</option>
                            <option value="+46" <?php echo (isset($_POST['landekode']) && $_POST['landekode'] == '+46') ? 'selected' : ''; ?>>+46</option>
                            <option value="+47" <?php echo (isset($_POST['landekode']) && $_POST['landekode'] == '+47') ? 'selected' : ''; ?>>+47</option>
                            <option value="+49" <?php echo (isset($_POST['landekode']) && $_POST['landekode'] == '+49') ? 'selected' : ''; ?>>+49</option>
                            <!-- Add more codes as needed -->
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label for="telefonnummer" class="block text-sm font-medium text-gray-700 mb-1">Telefonnummer</label>
                        <input type="tel" id="telefonnummer" name="telefonnummer"
                            class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500"
                            value="<?php echo isset($_POST['telefonnummer']) ? sanitizeOutput($_POST['telefonnummer']) : ''; ?>">
                    </div>
                </div>

                 <hr class="my-6 border-gray-300">

                <!-- Password -->
                <div class="mb-4">
                    <label for="kodeord" class="block text-sm font-medium text-gray-700 mb-1">Kodeord <span class="text-red-500">*</span></label>
                    <input type="password" id="kodeord" name="kodeord" required autocomplete="new-password"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mb-6">
                    <label for="bekraeft_kodeord" class="block text-sm font-medium text-gray-700 mb-1">Bekræft kodeord <span class="text-red-500">*</span></label>
                    <input type="password" id="bekraeft_kodeord" name="bekraeft_kodeord" required autocomplete="new-password"
                        class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Terms and Conditions -->
                <div class="mb-6 flex items-start">
                     <input type="checkbox" id="accept_terms" name="accept_terms" required class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1 mr-2">
                     <label for="accept_terms" class="text-sm text-gray-700">
                        Jeg bekræfter hermed at have læst og forstået <a href="/privacypolicy" target="_blank" class="underline text-blue-600 hover:text-blue-800">privatlivspolitikken</a> og <a href="/terms" target="_blank" class="underline text-blue-600 hover:text-blue-800">vilkårene</a>. <span class="text-red-500">*</span>
                     </label>
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black transition duration-150 ease-in-out mb-4">
                    Opret en konto
                </button>
            </form>
        </div>
    </div>

    <script>
        // Optional: Add client-side validation or interactions here
        const form = document.querySelector('form');
        const dobDay = document.getElementById('foedselsdag');
        const dobMonth = document.getElementById('foedselsmaaned');
        const dobYear = document.getElementById('foedselsaar');
        const termsCheckbox = document.getElementById('accept_terms');

        form.addEventListener('submit', (e) => {
             let isValid = true;
             // Example: Basic checkbox validation client-side
             if (!termsCheckbox.checked) {
                // You could display an error near the checkbox
                 alert('Du skal acceptere vilkårene for at fortsætte.');
                 isValid = false;
             }

             // Example: Basic Date validation (can be more complex)
             const day = parseInt(dobDay.value, 10);
             const month = parseInt(dobMonth.value, 10);
             const year = parseInt(dobYear.value, 10);

             if (dobDay.value || dobMonth.value || dobYear.value) { // Only validate if any part is filled
                 if (isNaN(day) || isNaN(month) || isNaN(year) || day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > new Date().getFullYear()) {
                     alert('Indtast venligst en gyldig fødselsdato (DD, MM, YYYY).');
                     isValid = false;
                 }
                 // A more robust check would involve checking days in month (e.g., Feb 30th is invalid)
             }


             if (!isValid) {
                 e.preventDefault(); // Stop form submission if validation fails
             }
        });
    </script>
</body>
</html>