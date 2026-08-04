<?php

session_start();

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../includes/sepay_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$orderId = (int) ($_GET['order'] ?? 0);

if ($userId <= 0 || $orderId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$status = sepay_order_status($conn, $orderId, $userId);
if ($status === null) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode(['status' => $status]);
