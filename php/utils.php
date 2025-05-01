<?php
// utils.php

require_once 'config.php';

/**
 * Opretter en databaseforbindelse ved brug af PDO.
 * @return PDO
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true, // Consider if persistent connection is truly needed
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        // Consider a more graceful shutdown or error page in production
        die("Der opstod en fejl ved oprettelse af databaseforbindelsen.");
    }
}

/**
 * Eksekverer en SQL-forespørgsel med forberedte udtryk.
 * @param string $query SQL-forespørgslen
 * @param array $params Parametre til forespørgslen
 * @return array|bool Resultatet fra en SELECT eller true/false for INSERT/UPDATE/DELETE
 */
function executeQuery($query, $params = []) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        // Check if it's a SELECT query more reliably
        if ($stmt->columnCount() > 0) {
             return $stmt->fetchAll();
        }
        // For INSERT/UPDATE/DELETE, check affected rows if needed, otherwise return true
        // return $stmt->rowCount() > 0; // More specific check if needed
        return true;
    } catch (PDOException $e) {
        $errorDetails = "Query: " . $query . " | Params: " . json_encode($params);
        error_log("Query Execution Error: " . $e->getMessage() . " | " . $errorDetails);
        return false; // Return false on error
    }
}

/**
 * Eksekverer en INSERT SQL-forespørgsel og returnerer det sidst indsatte ID.
 * @param string $query SQL-forespørgslen
 * @param array $params Parametre til forespørgslen
 * @return string|false Det sidst indsatte ID ved succes, ellers false. Note: lastInsertId returns a string.
 */
function executeInsertQuery($query, $params = []) {
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        // Return the last inserted ID
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        $errorDetails = "Query: " . $query . " | Params: " . json_encode($params);
        error_log("Insert Query Execution Error: " . $e->getMessage() . " | " . $errorDetails);
        return false; // Return false on error
    }
}

/**
 * Saniterer output for at forhindre XSS-angreb.
 * @param string $data Data der skal saniteres
 * @return string Sanitiseret data
 */
function sanitizeOutput($data) {
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sender en email.
 * @param string $to Modtagerens email-adresse
 * @param string $subject Emne for emailen
 * @param string $message Email-indholdet (HTML understøttes)
 * @param string $headers Valgfri ekstra headers
 * @return bool
 */
function sendEmail($to, $subject, $message, $headers = '') {
    $defaultHeaders = "From: " . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@example.com') . "\r\n";
    $defaultHeaders .= "Reply-To: " . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@example.com') . "\r\n";
    $defaultHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
    $defaultHeaders .= "X-Mailer: PHP/" . phpversion();
    $allHeaders = $headers ? $defaultHeaders . $headers : $defaultHeaders;

    $result = mail($to, $subject, $message, $allHeaders);
    if ($result) {
        logEvent('INFO', "Email sent successfully to $to with subject: $subject");
    } else {
        logEvent('ERROR', "Failed to send email to $to with subject: $subject");
    }
    return $result;
}

// --- MODIFIED registerUser FUNCTION ---
/**
 * Registrerer en ny bruger i systemet.
 * Takes parameters corresponding to the new English snake_case column names.
 * @return int|false Returnerer bruger-ID ved succes, ellers false
 */
function registerUser($first_name, $last_name, $gender, $email, $birth_date,
                     $street_name, $house_number, $floor, $postal_code, $city, $country,
                     $country_code, $phone_number, $password) {
    $checkQuery = "SELECT user_id FROM users WHERE email = :email LIMIT 1";
    $exists = executeQuery($checkQuery, ['email' => $email]);

    if ($exists === false) {
        logEvent('ERROR', "Database error checking email existence for $email");
        setFlashMessage('error', "Der opstod en databasefejl under tjek af email.");
        return false;
    }
    if ($exists && count($exists) > 0) {
        logEvent('WARNING', "Registration attempt with existing email: $email");
        setFlashMessage('error', "En bruger med denne email eksisterer allerede.");
        return false;
    }

    $validDate = null;
    if (!empty($birth_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $dateParts = explode('-', $birth_date);
        if (checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            $validDate = $birth_date;
        } else {
            logEvent('WARNING', "Invalid birth date format for $email: $birth_date");
            setFlashMessage('error', "Ugyldigt datoformat for fødselsdag (skal være YYYY-MM-DD).");
            return false;
        }
    } elseif (!empty($birth_date) && $birth_date !== '--') {
        logEvent('WARNING', "Invalid birth date format for $email: $birth_date");
        setFlashMessage('error', "Ugyldigt datoformat for fødselsdag (skal være YYYY-MM-DD).");
        return false;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        logEvent('ERROR', "Password hashing failed for $email");
        setFlashMessage('error', "Fejl under hashing af kodeord.");
        return false;
    }

    $query = "INSERT INTO users (first_name, last_name, email, phone_number, password, role,
                                gender, birth_date, street_name, house_number, floor, postal_code,
                                city, country, country_code)
              VALUES (:first_name, :last_name, :email, :phone_number, :password, 'user',
                      :gender, :birth_date, :street_name, :house_number, :floor, :postal_code,
                      :city, :country, :country_code)";
    $params = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone_number' => $phone_number ?: null,
        'password' => $hashedPassword,
        'gender' => $gender ?: null,
        'birth_date' => $validDate,
        'street_name' => $street_name ?: null,
        'house_number' => $house_number ?: null,
        'floor' => $floor ?: null,
        'postal_code' => $postal_code ?: null,
        'city' => $city ?: null,
        'country' => $country ?: null,
        'country_code' => $country_code ?: '+45'
    ];

    $userId = executeInsertQuery($query, $params);

    if ($userId) {
        logEvent('INFO', "User registered successfully: $email (ID: $userId)");
        return $userId;
    } else {
        logEvent('ERROR', "Failed to register user: $email");
        setFlashMessage('error', "Kunne ikke oprette bruger i databasen.");
        return false;
    }
}


/**
 * Eksempel på en funktion til brugerautentificering.
 * Tjekker om email og password matcher en post i databasen.
 * @param string $email Brugerens email
 * @param string $password Brugerens password
 * @return array|false Returnerer brugerdata (array) ved succes, ellers false.
 */
function authenticateUser($email, $password) {
    $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $result = executeQuery($query, ['email' => $email]);

    if ($result === false) {
        logEvent('ERROR', "Database error during authentication for $email");
        return false;
    }

    if ($result && count($result) > 0) {
        $user = $result[0];
        if (password_verify($password, $user['password'])) {
            logEvent('INFO', "User authenticated successfully: $email");
            unset($user['password']);
            return $user;
        } else {
            logEvent('SECURITY', "Failed authentication attempt for $email: incorrect password");
        }
    } else {
        logEvent('SECURITY', "Failed authentication attempt for $email: user not found");
    }
    return false;
}


/**
 * Omdirigerer til en given URL.
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Sætter en flash-besked med type.
 * @param string $type Type af besked (success, error, warning, info)
 * @param string $message Besked
 */
function setFlashMessage($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Ensure session is started
    }
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Viser og rydder flash-beskeder.
 */
function displayFlashMessages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Ensure session is started
    }
    if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            // Ensure $flash is an array with expected keys
            if (!is_array($flash) || !isset($flash['message'])) continue;

            $type = isset($flash['type']) ? sanitizeOutput($flash['type']) : 'info';
            $message = sanitizeOutput($flash['message']);

            $bgColor = 'bg-blue-100 border-blue-400 text-blue-700'; // Default info style

            switch ($type) {
                case 'success':
                    $bgColor = 'bg-green-100 border-green-400 text-green-700';
                    break;
                case 'error':
                    $bgColor = 'bg-red-100 border-red-400 text-red-700';
                    break;
                case 'warning':
                    $bgColor = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                    break;
            }

            echo '<div class="' . $bgColor . ' px-4 py-3 rounded relative mb-4" role="alert">';
            echo '<span class="block sm:inline">' . $message . '</span>';
            // Optional: Add a close button
            // echo '<button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove();">×</button>';
            echo '</div>';
        }
        unset($_SESSION['flash_messages']); // Clear messages after displaying
    }
}

/**
 * Tilføjer en simpel flash-besked (default type 'info').
 * @param string $message Besked der skal vises
 */
function addFlashMessage($message) {
    setFlashMessage('info', $message); // Use the typed version
}


/**
 * Genererer en sikker token.
 * @param int $length Længden af tokenet i bytes (resulterer i 2*length hex characters)
 * @return string|false Token string or false on failure
 */
function generateSecureToken($length = 32) {
     try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
         error_log("Failed to generate secure token: " . $e->getMessage());
        return false;
    }
}


/**
 * Håndterer filuploads (basic example).
 * Consider adding more validation (size, renaming files, etc.)
 * @param array $file $_FILES['input_name'] array
 * @param string $uploadDir Target directory (ensure it ends with a slash)
 * @param array $allowedTypes Array of allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return string|false Uploaded file path on success, false on failure
 */
function uploadFile($file, $uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5 * 1024 * 1024) { // 5MB default max size
    if (!isset($file['error']) || is_array($file['error'])) {
        return false; // Invalid parameters
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return false; // No file sent
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return false; // Exceeded filesize limit
        default:
            return false; // Unknown error
    }

    if ($file['size'] > $maxSize) {
        return false; // Exceeded max size limit
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (false === array_search($mimeType, $allowedTypes, true)) {
        return false; // Invalid file type
    }

    // Generate a unique filename to prevent overwriting and security issues
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = bin2hex(random_bytes(8)) . '.' . strtolower($extension);
    $destination = $uploadDir . $safeFilename;

    // Ensure upload directory exists and is writable
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
         return false;
    }


    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $destination; // Return the path to the uploaded file
    } else {
        return false; // Failed to move file
    }
}

/**
 * Logs an event to the database.
 * @param string $eventType Type of event (INFO, WARNING, ERROR, SECURITY, TICKET)
 * @param string $message Description of the event
 * @param int|null $userId User associated with the event (optional)
 * @param int|null $ticketId Ticket associated with the event (optional)
 * @param int|null $matchId Match associated with the event (optional)
 * @return bool True on success, false on failure
 */
function logEvent($eventType, $message, $userId = null, $ticketId = null, $matchId = null) {
    $validEventTypes = ['INFO', 'WARNING', 'ERROR', 'SECURITY', 'TICKET'];
    if (!in_array($eventType, $validEventTypes)) {
        error_log("Invalid event type: $eventType");
        return false;
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $query = "INSERT INTO logs (event_type, message, user_id, ip_address, ticket_id, match_id)
              VALUES (:eventType, :message, :userId, :ipAddress, :ticketId, :matchId)";
    $params = [
        'eventType' => $eventType,
        'message' => $message,
        'userId' => $userId ?: null,
        'ipAddress' => $ipAddress,
        'ticketId' => $ticketId ?: null,
        'matchId' => $matchId ?: null,
    ];

    $result = executeQuery($query, $params);
    if ($result === false) {
        error_log("Failed to log event: $eventType - $message");
    }
    return $result;
}

?>