<?php
// api/chat/session_action.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_proxy_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

if (!chat_admin_csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf_token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = isset($input['session_id']) ? (int) $input['session_id'] : 0;
$action = (string) ($input['action'] ?? '');

if ($sessionId <= 0 || !in_array($action, ['claim', 'close', 'reopen'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_session_action']);
    exit;
}

$payload = [
    'session_id' => $sessionId,
    'admin_id' => (int) $_SESSION['admin_id'],
    'action' => $action,
];

$headers = ['Content-Type: application/json'];
$bypass = chat_admin_bypass_header();
if ($bypass !== null) {
    $headers[] = $bypass;
}

$ch = curl_init(chat_ai_service_url() . '/admin/session-action');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'ai_service_unavailable']);
    exit;
}

http_response_code($code ?: 200);
echo $body;
