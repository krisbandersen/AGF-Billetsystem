<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../php/utils.php'; // Assuming utils.php is needed for potential form processing logic later

// --- MODIFIED SESSION CHECK ---
if (!isset($_SESSION['user_id'])) { // Changed from BrugerID
    header('Location: logout.php');
    exit;
}
// --- END MODIFICATION ---

$message = '';
$messageType = ''; // Can be 'success', 'error', 'warning'

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $userId = $_SESSION['user_id'];

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'Alle adgangskodefelter skal udfyldes.';
        $messageType = 'error';
        logEvent('WARNING', "Password change attempt failed for user ID $userId: missing required fields");
    } elseif (strlen($newPassword) < 8) {
        $message = 'Den nye adgangskode skal være mindst 8 tegn lang.';
        $messageType = 'error';
        logEvent('WARNING', "Password change attempt failed for user ID $userId: new password too short");
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'De nye adgangskoder stemmer ikke overens.';
        $messageType = 'error';
        logEvent('WARNING', "Password change attempt failed for user ID $userId: passwords do not match");
    } elseif ($newPassword === $currentPassword) {
        $message = 'Den nye adgangskode skal være forskellig fra den nuværende.';
        $messageType = 'error';
        logEvent('WARNING', "Password change attempt failed for user ID $userId: new password same as current");
    } else {
        try {
            $sqlFetch = "SELECT password FROM users WHERE user_id = :userid LIMIT 1";
            $userData = executeQuery($sqlFetch, ['userid' => $userId]);

            if ($userData && count($userData) > 0) {
                $currentUser = $userData[0];
                $storedHash = $currentUser['password'];

                if (password_verify($currentPassword, $storedHash)) {
                    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $sqlUpdate = "UPDATE users SET password = :newpassword WHERE user_id = :userid";
                    $updated = executeQuery($sqlUpdate, [
                        'newpassword' => $newHashedPassword,
                        'userid' => $userId
                    ]);

                    if ($updated) {
                        $message = 'Din adgangskode er blevet opdateret!';
                        $messageType = 'success';
                        logEvent('INFO', "Password updated successfully for user ID $userId");
                        $_POST = [];
                    } else {
                        $message = 'Der opstod en uventet fejl under opdatering af adgangskoden. Prøv igen senere.';
                        $messageType = 'error';
                        logEvent('ERROR', "Password update failed for user ID $userId: executeQuery returned false");
                    }
                } else {
                    $message = 'Den nuværende adgangskode er forkert.';
                    $messageType = 'error';
                    logEvent('SECURITY', "Password change attempt failed for user ID $userId: incorrect current password");
                }
            } else {
                $message = 'Brugerkonto blev ikke fundet. Log venligst ind igen.';
                $messageType = 'error';
                logEvent('ERROR', "Password change attempt failed: user ID $userId not found");
            }
        } catch (PDOException $e) {
            $message = 'Databasefejl under opdatering af adgangskode. Kontakt support.';
            $messageType = 'error';
            logEvent('ERROR', "Database error during password update for user ID $userId: " . $e->getMessage());
        } catch (Exception $e) {
            $message = 'En generel fejl opstod. Prøv igen senere.';
            $messageType = 'error';
            logEvent('ERROR', "General error during password update for user ID $userId: " . $e->getMessage());
        }
    }
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
    <title data-lang-key="pageTitleChangePassword">AGF - Min Konto - Skift Adgangskode</title>
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
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include '../layout/navbar.php'; ?>

    <main class="container mx-auto my-8 px-4">
        <div class="bg-white rounded-lg shadow-md p-6 md:p-8">
            <nav class="mb-6 pb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center text-gray-500">
                    <?php foreach ($accountNavItems as $title => $file): ?>
                        <li class="mr-2">
                            <?php
                                $isActive = ($currentPage == $file);
                                $linkClasses = 'inline-block p-4 rounded-t-lg border-b-2 ';
                                if ($isActive) {
                                    $linkClasses .= 'text-blue-600 border-blue-600 active';
                                } else {
                                    $linkClasses .= 'border-transparent hover:text-gray-600 hover:border-gray-300';
                                }
                            ?>
                            <a href="<?php echo htmlspecialchars($file); ?>" class="<?php echo $linkClasses; ?>" <?php if($isActive) echo 'aria-current="page"'; ?>>
                                <?php echo htmlspecialchars($title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="mt-6">
                <h2 class="text-2xl font-semibold mb-4" data-lang-key="changePasswordHeading">Skift Adgangskode</h2>

                <?php if (!empty($message)): ?>
                    <div class="mb-4 px-4 py-3 rounded relative
                        <?php echo ($messageType === 'success') ? 'bg-green-100 border border-green-400 text-green-700' : ''; ?>
                        <?php echo ($messageType === 'error') ? 'bg-red-100 border border-red-400 text-red-700' : ''; ?>
                        <?php echo ($messageType === 'warning') ? 'bg-yellow-100 border border-yellow-400 text-yellow-700' : ''; ?>"
                         role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4 max-w-md">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700" data-lang-key="currentPasswordLabel">Nuværende Adgangskode</label>
                        <input type="password" id="current_password" name="current_password" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700" data-lang-key="newPasswordLabel">Ny Adgangskode</label>
                        <input type="password" id="new_password" name="new_password" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700" data-lang-key="confirmPasswordLabel">Bekræft Ny Adgangskode</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                data-lang-key="updatePasswordBtn">
                            Opdater Adgangskode
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <?php include '../layout/footer.php'; ?>

    <script>
      const langButton = document.getElementById('language-button');
      const langMenu = document.getElementById('language-menu');
      const currentLangSpan = document.getElementById('current-language');
      const langOptions = document.querySelectorAll('.language-option');

       langButton.addEventListener('click', (event) => {
        event.stopPropagation();
        const isExpanded = langButton.getAttribute('aria-expanded') === 'true';
        langMenu.classList.toggle('hidden');
        langButton.setAttribute('aria-expanded', !isExpanded);
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
    </script>
</body>
</html>