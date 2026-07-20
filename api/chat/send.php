<?php
// api/chat/send.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_proxy_helpers.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (trim((string) ($input['message'] ?? '')) === '') {
    http_response_code(422);
    echo json_encode(['error' => 'message required']);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$payload = chat_build_forward_payload($input, $userId);

$ch = curl_init(chat_ai_service_url() . '/chat/send');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($body === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'ai_service_unavailable',
        'reply' => ['content' => 'Hệ thống chat đang bận, bạn vui lòng thử lại hoặc gọi hotline 0901 234 567.']
    ]);
    exit;
}

http_response_code($code ?: 200);
echo $body;
