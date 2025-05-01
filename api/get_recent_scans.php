<?php
require_once '../php/utils.php'; // Adjust path if your api folder is elsewhere

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required to view recent scans']);
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
    $recentScansQuery = "SELECT l.log_id, l.timestamp, l.message, l.ticket_id, l.match_id
                         FROM logs l
                         WHERE l.event_type = 'TICKET'
                         ORDER BY l.timestamp DESC
                         LIMIT 10"; // Fetch same number as initial load, or adjust

    $recentScans = executeQuery($recentScansQuery);

    if ($recentScans === false) {
        // executeQuery likely logged the DB error already in utils.php
        throw new Exception("Failed to retrieve recent scans from database.");
    }

    // Return the results as JSON
    // The default MySQL DATETIME format ('YYYY-MM-DD HH:MM:SS') is usually fine for JS new Date()
    echo json_encode($recentScans ?: []); // Return empty array if no results

} catch (Exception $e) {
    // Log the error if it wasn't a DB error handled by executeQuery
    if (strpos($e->getMessage(), 'database') === false) { // Avoid double logging DB errors
      error_log("Error fetching recent scans: " . $e->getMessage()); // Log generic errors
    }
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Kunne ikke hente seneste scans.']);
}
?>