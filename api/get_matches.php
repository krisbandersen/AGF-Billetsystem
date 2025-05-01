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

try {
    // Fetch matches ordered by date (newest first)
    $query = "SELECT match_id, DATE_FORMAT(match_datetime, '%Y-%m-%dT%H:%i') as match_datetime_local, opponent, stadium, card_url
              FROM matches
              ORDER BY match_datetime DESC";
    $matches = executeQuery($query);

    if ($matches === false) {
        throw new Exception("Failed to retrieve matches from database.");
    }

    echo json_encode($matches);

} catch (Exception $e) {
    logEvent('ERROR', "Error fetching matches for admin panel: " . $e->getMessage(), $userId);
    http_response_code(500);
    echo json_encode(['error' => 'Kunne ikke hente kampliste.']);
}
?>