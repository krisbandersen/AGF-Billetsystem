<?php
session_start();
require_once 'php/utils.php';

if (isset($_SESSION['user_id'])) {
    redirect('account/tickets.php');
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Email og kodeord må ikke være tomme.";
        logEvent('WARNING', "Login attempt failed: email or password empty");
    } else {
        $user = authenticateUser($email, $password);

        if ($user && is_array($user)) {
            session_regenerate_id(true);

            foreach ($user as $key => $value) {
                if ($key !== 'password') {
                    $_SESSION[$key] = $value;
                }
            }

            if (!isset($_SESSION['user_id'])) {
                logEvent('ERROR', "CRITICAL: User authenticated but user_id not set in session for email: $email");
                $error_message = "Loginfejl. Prøv igen.";
                session_destroy();
            } else {
                logEvent('INFO', "User login successful: $email (ID: {$_SESSION['user_id']})");
                redirect('account/tickets.php');
            }
        } else {
            $error_message = "Ugyldig email eller kodeord.";
            logEvent('SECURITY', "User login failed for email: $email");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log ind - AGF</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="font-sans bg-[url(https://robostaticcontent.s3.amazonaws.com/Content/AGF/Images/bg_TestServ.jpg)] bg-cover bg-center bg-no-repeat">
    <div class="min-h-screen flex flex-col items-center justify-center pt-12 pb-8 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center space-x-4 mb-10">
            <img src="assets/agf-logo.png" alt="AGF Logo" class="h-24 sm:h-32">
        </div>
        <div class="bg-white p-6 sm:p-8 rounded-sm shadow-lg w-full max-w-md">
            <div class="flex flex-col sm:flex-row justify-between items-baseline mb-6 gap-2">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Log ind</h2>
                <div class="text-sm whitespace-nowrap">
                    <span>eller </span>
                    <button onclick="window.location.href='register.php'" class="font-bold hover:text-gray-600 border-b-2 border-black hover:border-gray-900 pb-0.5 focus:outline-none cursor-pointer">
                        registrer
                    </button>
                </div>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?php echo sanitizeOutput($error_message); ?></span>
                </div>
            <?php endif; ?>
            <?php displayFlashMessages(); ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['email']) ? sanitizeOutput($_POST['email']) : ''; ?>">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Kodeord</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" class="w-full border border-gray-300 p-3 rounded-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black transition duration-150 ease-in-out">
                    Log ind
                </button>
            </form>
            <div class="mt-5 text-left">
                <a href="forgot-password.php" class="text-sm font-bold hover:text-gray-600 border-b-2 border-black hover:border-gray-900 pb-0.5 focus:outline-none cursor-pointer">
                    Glemt password?
                </a>
            </div>
        </div>
    </div>
    <script>
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const errorBox = document.querySelector('.bg-red-100');

        function clearErrorOnChange() {
            if (errorBox) {
                errorBox.style.display = 'none';
            }
        }

        if (emailInput) emailInput.addEventListener('input', clearErrorOnChange);
        if (passwordInput) passwordInput.addEventListener('input', clearErrorOnChange);
    </script>
</body>
</html>