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

// Use POST for simplicity, expect 'match_id' in the body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get match_id from POST body (assuming x-www-form-urlencoded or FormData)
$matchId = $_POST['match_id'] ?? null;

if (empty($matchId) || !filter_var($matchId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing match ID']);
    exit;
}

// Check for dependencies (e.g., tickets linked to this match) - IMPORTANT
// You might want to prevent deletion if tickets exist, or handle cascading deletes in the DB.
$checkTicketsQuery = "SELECT COUNT(*) as count FROM tickets WHERE match_id = :match_id";
$ticketCheckResult = executeQuery($checkTicketsQuery, ['match_id' => $matchId]);

if ($ticketCheckResult !== false && isset($ticketCheckResult[0]['count']) && $ticketCheckResult[0]['count'] > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'Kan ikke slette kampen, da der er tilknyttede billetter. Slet billetterne først.']);
    exit;
}
// Add checks for other dependencies (logs?) if necessary


$query = "DELETE FROM matches WHERE match_id = :match_id";
$params = ['match_id' => $matchId];

try {
    $success = executeQuery($query, $params); // executeQuery should return true on success for DELETE

    if ($success) {
        logEvent('INFO', "Match ID $matchId deleted successfully by user ID $userId", $userId, null, $matchId);
        echo json_encode(['success' => true]);
    } else {
        // executeQuery returning false might indicate the ID didn't exist or a PDOException was caught inside
        logEvent('ERROR', "Failed to delete match ID $matchId by user ID $userId (executeQuery returned false or ID not found)", $userId, null, $matchId);
        // Determine if it was "not found" vs actual error if possible
        http_response_code(404); // Not Found (or 500 if it was a DB error)
        echo json_encode(['error' => 'Kunne ikke slette kampen (findes måske ikke).']);
    }
} catch (PDOException $e) {
     // Catch specific foreign key constraint errors if needed
     if (strpos($e->getMessage(), 'FOREIGN KEY constraint fails') !== false) {
         logEvent('ERROR', "Foreign key constraint error deleting match ID $matchId by user ID $userId: " . $e->getMessage(), $userId, null, $matchId);
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Kan ikke slette kampen pga. afhængigheder (f.eks. billetter eller logs).']);
     } else {
         logEvent('ERROR', "Database error deleting match ID $matchId by user ID $userId: " . $e->getMessage(), $userId, null, $matchId);
         http_response_code(500);
         echo json_encode(['error' => 'Databasefejl ved sletning af kamp.']);
     }
} catch (Exception $e) {
    logEvent('ERROR', "General error deleting match ID $matchId by user ID $userId: " . $e->getMessage(), $userId, null, $matchId);
    http_response_code(500);
    echo json_encode(['error' => 'En uventet fejl opstod under sletning.']);
}
?>