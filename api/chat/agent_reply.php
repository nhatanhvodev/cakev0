<?php
// Authenticated admin reply proxy.
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

if (!chat_admin_csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf_token']);
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

$content = function_exists('mb_substr')
    ? mb_substr($content, 0, 5000, 'UTF-8')
    : substr($content, 0, 5000);

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../includes/admin_chat_repository.php';

admin_chat_json(admin_chat_reply_response($conn, $sessionId, (int) $_SESSION['admin_id'], $content));
