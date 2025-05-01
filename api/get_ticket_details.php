<?php
require_once '../php/utils.php'; // Ensure this path is correct

header('Content-Type: application/json');

// Validate input parameters
$ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT);
$matchId = filter_input(INPUT_GET, 'match_id', FILTER_VALIDATE_INT);

if ($ticketId === false || $ticketId === null || $matchId === false || $matchId === null) {
    // Send 400 Bad Request for invalid/missing input
    http_response_code(400);
    // Log this event as it might indicate issues with the QR code or frontend call
    logEvent('WARNING', 'Invalid or missing ticket_id/match_id in get_ticket_details request', null, $_GET['ticket_id'] ?? null, $_GET['match_id'] ?? null);
    echo json_encode(['error' => 'Valid ticket_id and match_id are required']);
    exit;
}

// --- CORRECTED QUERY ---
// Select m.opponent and alias it AS match_name
$query = "SELECT t.ticket_type, t.section, t.`row`, t.seat_number, m.opponent AS match_name
          FROM tickets t
          JOIN matches m ON t.match_id = m.match_id
          WHERE t.ticket_id = :ticketId AND t.match_id = :matchId";

$params = ['ticketId' => $ticketId, 'matchId' => $matchId];

try {
    $result = executeQuery($query, $params);

    if ($result === false) {
         // executeQuery failed (likely PDOException caught in utils.php and logged)
         http_response_code(500); // Internal Server Error
         echo json_encode(['error' => 'Database error retrieving ticket details']);
         exit;
    }

    if (count($result) > 0) {
        $ticket = $result[0];

        // Prepare data for JSON output, explicitly handling potential NULLs
        $output = [
            // Use the alias 'match_name' which now contains the opponent's name
            'match_name'  => $ticket['match_name'] ? htmlspecialchars($ticket['match_name']) : null,
            'section'     => $ticket['section'] ? htmlspecialchars($ticket['section']) : null,
            'row'         => $ticket['row'] ? htmlspecialchars($ticket['row']) : null,
            'seat_number' => $ticket['seat_number'] !== null ? (int)$ticket['seat_number'] : null // Cast seat to int if not null
            // You could also include ticket_type if needed by the frontend
            // 'ticket_type' => $ticket['ticket_type'] ? htmlspecialchars($ticket['ticket_type']) : null,
        ];
        echo json_encode($output);

    } else {
        // Ticket/Match combination not found
        http_response_code(404); // Not Found
        // Log this specific scenario
        logEvent('INFO', "Ticket details not found for ticket_id $ticketId and match_id $matchId", null, $ticketId, $matchId);
        echo json_encode(['error' => 'Ticket details not found for the specified match']);
    }

} catch (Exception $e) {
    // Catch any other unexpected errors
    logEvent('ERROR', "Unexpected error in get_ticket_details: " . $e->getMessage(), null, $ticketId, $matchId);
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
}
?>