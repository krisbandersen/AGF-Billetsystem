<?php
require_once '../php/utils.php';

header('Content-Type: application/json');

$today = date('Y-m-d');
$query = "SELECT 
             COUNT(*) as total_scans,
             SUM(CASE WHEN message LIKE '%successfully%' THEN 1 ELSE 0 END) as valid_scans,
             SUM(CASE WHEN message LIKE '%failed%' THEN 1 ELSE 0 END) as invalid_scans
          FROM logs 
          WHERE event_type = 'TICKET' AND DATE(timestamp) = :today";
$stats = executeQuery($query, ['today' => $today])[0] ?: ['total_scans' => 0, 'valid_scans' => 0, 'invalid_scans' => 0];

echo json_encode([
    'total_scans' => (int)$stats['total_scans'],
    'valid_scans' => (int)$stats['valid_scans'],
    'invalid_scans' => (int)$stats['invalid_scans']
]);
?>