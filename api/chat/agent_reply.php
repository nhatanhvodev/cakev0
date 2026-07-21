<?php
// api/chat/agent_reply.php
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = isset($input['session_id']) ? (int) $input['session_id'] : 0;
$content = trim((string) ($input['content'] ?? ''));

if ($sessionId <= 0 || $content === '') {
    http_response_code(422);
    echo json_encode(['error' => 'session_id and content are required']);
    exit;
}

// admin_id is injected server-side from the session — never trust the client payload.
$payload = [
    'session_id' => $sessionId,
    'admin_id' => (int) $_SESSION['admin_id'],
    'content' => $content,
];

$ch = curl_init(chat_ai_service_url() . '/admin/reply');
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
    echo json_encode(['error' => 'ai_service_unavailable']);
    exit;
}

http_response_code($code ?: 200);
echo $body;
