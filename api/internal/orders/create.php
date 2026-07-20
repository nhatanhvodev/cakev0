<?php
// api/internal/orders/create.php
require_once __DIR__ . '/../../../config/connect.php';
require_once __DIR__ . '/../../../includes/internal_order_api.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'method']); exit; }

$secret = getenv('INTERNAL_API_SECRET') ?: '';
$body = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] ?? null;
if (!internal_api_verify_signature($body, $sig, $secret)) {
    http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit;
}
$payload = json_decode($body, true) ?: [];
$result = create_order_internal($conn, $payload);   // $conn từ bootstrap/db connect hiện có
if (isset($result['error'])) { http_response_code(422); }
echo json_encode($result, JSON_UNESCAPED_UNICODE);
