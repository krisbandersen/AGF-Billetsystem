<?php
require_once '../php/utils.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$matchDateTime = $_POST['match_date'] ?? '';
$opponent = trim($_POST['opponent'] ?? '');
$stadium = trim($_POST['stadium'] ?? '');
$cardUrl = trim($_POST['card_url'] ?? '');

if (empty($matchDateTime) || empty($opponent) || empty($stadium) || empty($cardUrl)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Alle felter skal udfyldes']);
    exit;
}

if (!filter_var($cardUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Ugyldig URL til kort']);
    exit;
}

// Validate date format (optional but recommended)
// Example: Check if it's a valid YYYY-MM-DD HH:MM:SS format
// $d = DateTime::createFromFormat('Y-m-d H:i:s', $matchDateTime);
// if (!$d || $d->format('Y-m-d H:i:s') !== $matchDateTime) {
//     http_response_code(400);
//     echo json_encode(['error' => 'Ugyldigt dato/tid format. Brug YYYY-MM-DD HH:MM:SS']);
//     exit;
// }


$query = "INSERT INTO matches (match_datetime, opponent, stadium, card_url)
          VALUES (:match_datetime, :opponent, :stadium, :card_url)";

$params = [
    'match_datetime' => $matchDateTime,
    'opponent'       => $opponent,
    'stadium'        => $stadium,
    'card_url'       => $cardUrl
];

try {
    // Assuming executeInsertQuery is defined in utils.php and handles DB connection/execution
    $matchId = executeInsertQuery($query, $params);

    if ($matchId) {
        logEvent('INFO', "Match added successfully by user ID $userId: $opponent at $stadium on $matchDateTime", $userId, null, $matchId);
        echo json_encode(['success' => true, 'match_id' => $matchId]);
    } else {
        logEvent('ERROR', "Failed to add match (executeInsertQuery returned false) by user ID $userId: $opponent at $stadium on $matchDateTime", $userId);
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Kunne ikke tilføje kampen til databasen']);
    }
} catch (PDOException $e) {
    logEvent('ERROR', "Database error adding match by user ID $userId: " . $e->getMessage(), $userId);
    http_response_code(500);
    echo json_encode(['error' => 'Databasefejl ved tilføjelse af kamp']);
} catch (Exception $e) {
    logEvent('ERROR', "General error adding match by user ID $userId: " . $e->getMessage(), $userId);
    http_response_code(500);
    echo json_encode(['error' => 'En uventet fejl opstod']);
}

?>