<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php/utils.php';

// --- MODIFIED USER IDENTIFIER LOGIC ---
// Try to get user_id first, then email, then fallback
$userIdentifier = $_SESSION['user_id'] ?? ($_SESSION['email'] ?? 'Unknown User');
logEvent('INFO', "User logout initiated for: $userIdentifier");
// --- END MODIFICATION ---


// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set expiration in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
redirect('login.php'); // Ensure this path is correct relative to logout.php
?>