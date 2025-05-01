<?php
require_once '../php/utils.php'; // Adjust path as needed

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure user is logged in and is an admin
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$sql = "SELECT role FROM users WHERE user_id = :userId";
$userData = executeQuery($sql, ['userId' => $userId]);
if (!$userData || empty($userData) || $userData[0]['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get data from POST request
$matchId = $_POST['edit_match_id'] ?? null; // Use a specific name for the ID in edit form
$matchDateTime = $_POST['edit_match_date'] ?? '';
$opponent = trim($_POST['edit_opponent'] ?? '');
$stadium = trim($_POST['edit_stadium'] ?? '');
$cardUrl = trim($_POST['edit_card_url'] ?? '');

// Validate input
if (empty($matchId) || empty($matchDateTime) || empty($opponent) || empty($stadium) || empty($cardUrl)) {
    http_response_code(400);
    echo json_encode(['error' => 'Alle felter skal udfyldes']);
    exit;
}

if (!filter_var($cardUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig URL til kort']);
    exit;
}

// Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL DATETIME format (YYYY-MM-DD HH:MM:SS)
$dateTimeObj = DateTime::createFromFormat('Y-m-d\TH:i', $matchDateTime);
if (!$dateTimeObj) {
     http_response_code(400);
     echo json_encode(['error' => 'Ugyldigt dato/tid format.']);
     exit;
}
$mysqlDateTime = $dateTimeObj->format('Y-m-d H:i:s');


$query = "UPDATE matches
          SET match_datetime = :match_datetime,
              opponent = :opponent,
              stadium = :stadium,
              card_url = :card_url
          WHERE match_id = :match_id";

$params = [
    'match_datetime' => $mysqlDateTime,
    'opponent'       => $opponent,
    'stadium'        => $stadium,
    'card_url'       => $cardUrl,
    'match_id'       => $matchId
];

try {
    $success = executeQuery($query, $params); // executeQuery should return true on success for UPDATE

    if ($success) {
        logEvent('INFO', "Match ID $matchId updated successfully by user ID $userId", $userId, null, $matchId);
        // Return the updated match data (formatted for consistency)
        echo json_encode([
            'success' => true,
            'match' => [
                'match_id' => $matchId,
                'match_datetime_local' => $matchDateTime, // Return the input format
                'opponent' => $opponent,
                'stadium' => $stadium,
                'card_url' => $cardUrl
            ]
        ]);
    } else {
        // executeQuery returning false usually means PDOException caught inside it
        logEvent('ERROR', "Failed to update match ID $matchId by user ID $userId (executeQuery returned false)", $userId, null, $matchId);
        http_response_code(500);
        echo json_encode(['error' => 'Kunne ikke opdatere kampen i databasen.']);
    }
} catch (PDOException $e) {
    logEvent('ERROR', "Database error updating match ID $matchId by user ID $userId: " . $e->getMessage(), $userId, null, $matchId);
    http_response_code(500);
    echo json_encode(['error' => 'Databasefejl ved opdatering af kamp.']);
} catch (Exception $e) {
    logEvent('ERROR', "General error updating match ID $matchId by user ID $userId: " . $e->getMessage(), $userId, null, $matchId);
    http_response_code(500);
    echo json_encode(['error' => 'En uventet fejl opstod under opdatering.']);
}
?>