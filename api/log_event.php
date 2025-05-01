<?php
require_once '../php/utils.php';

header('Content-Type: application/json');

$eventType = $_POST['event_type'] ?? '';
$message = $_POST['message'] ?? '';
$userId = $_POST['user_id'] ?? null;
$ticketId = $_POST['ticket_id'] ?: null;
$matchId = $_POST['match_id'] ?: null;

$result = logEvent($eventType, $message, $userId, $ticketId, $matchId);
echo json_encode(['success' => $result]);
?>