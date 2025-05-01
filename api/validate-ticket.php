<?php
header('Content-Type: application/json');
require_once '../php/utils.php';

// Secret key for HMAC
$secretKey = defined('TICKET_SECRET_KEY');

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logEvent('WARNING', 'Invalid request method for ticket validation');
    echo json_encode(['valid' => false, 'reason' => 'Only POST requests are allowed']);
    exit;
}

// Get QR code data from POST request
$qrText = $_POST['qrText'] ?? null;
if (!$qrText) {
    logEvent('WARNING', 'No QR code data provided in request');
    echo json_encode(['valid' => false, 'reason' => 'No QR code data provided']);
    exit;
}

// Parse QR code data
$parts = explode('|', $qrText, 3);
if (count($parts) !== 3) {
    logEvent('WARNING', "Invalid QR code format: $qrText");
    echo json_encode(['valid' => false, 'reason' => 'Invalid QR code format']);
    exit;
}

// Cast to integers
$matchId = (int)$parts[0];
$ticketId = (int)$parts[1];
$providedHmac = $parts[2];

// Recompute HMAC
$message = "$matchId|$ticketId";
$computedHmac = hash_hmac('sha256', $message, $secretKey);

// Compare HMACs securely
if (!hash_equals($computedHmac, $providedHmac)) {
    logEvent('SECURITY', "HMAC verification failed for ticket ID $ticketId, match ID $matchId", null, $ticketId, $matchId);
    echo json_encode(['valid' => false, 'reason' => 'HMAC verification failed']);
    exit;
}

// Database validation
try {
    // Step 1: Check if the ticket exists
    $checkExistsQuery = "SELECT COUNT(*) as count FROM tickets WHERE ticket_id = :ticketId AND match_id = :matchId";
    $existsResult = executeQuery($checkExistsQuery, ['ticketId' => $ticketId, 'matchId' => $matchId]);

    if ($existsResult === false) {
        logEvent('ERROR', "Database error checking ticket existence for ticket ID $ticketId, match ID $matchId");
        echo json_encode(['valid' => false, 'reason' => 'Database error while checking ticket existence']);
        exit;
    }

    if ($existsResult[0]['count'] == 0) {
        logEvent('TICKET', "Ticket does not exist: ticket ID $ticketId, match ID $matchId", null, $ticketId, $matchId);
        echo json_encode(['valid' => false, 'reason' => 'Ticket does not exist']);
        exit;
    }

    // Step 2: Check if the ticket is unused
    $checkUsedQuery = "SELECT is_used FROM tickets WHERE ticket_id = :ticketId AND match_id = :matchId";
    $usedResult = executeQuery($checkUsedQuery, ['ticketId' => $ticketId, 'matchId' => $matchId]);

    if ($usedResult === false) {
        logEvent('ERROR', "Database error checking ticket status for ticket ID $ticketId, match ID $matchId");
        echo json_encode(['valid' => false, 'reason' => 'Database error while checking ticket status']);
        exit;
    }

    if ($usedResult[0]['is_used'] == 1) {
        logEvent('TICKET', "Ticket already used: ticket ID $ticketId, match ID $matchId", null, $ticketId, $matchId);
        echo json_encode(['valid' => false, 'reason' => 'Ticket has already been used']);
        exit;
    }

    // Step 3: Mark ticket as used
    $updateQuery = "UPDATE tickets SET is_used = 1 WHERE ticket_id = :ticketId AND match_id = :matchId";
    $updateResult = executeQuery($updateQuery, ['ticketId' => $ticketId, 'matchId' => $matchId]);

    if ($updateResult === false) {
        logEvent('ERROR', "Failed to mark ticket as used: ticket ID $ticketId, match ID $matchId", null, $ticketId, $matchId);
        echo json_encode(['valid' => false, 'reason' => 'Failed to mark ticket as used']);
        exit;
    }

    // Success: Log the successful validation
    logEvent('TICKET', "Ticket validated and marked as used: ticket ID $ticketId, match ID $matchId", null, $ticketId, $matchId);
    echo json_encode(['valid' => true]);
} catch (Exception $e) {
    logEvent('ERROR', "Database exception: " . $e->getMessage(), null, $ticketId, $matchId);
    echo json_encode(['valid' => false, 'reason' => 'Database error occurred']);
}
?>