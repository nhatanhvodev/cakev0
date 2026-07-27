<?php
// Filtered admin session-list proxy.
// api/chat/sessions.php
require_once __DIR__ . '/../../config/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$allowedViews = ['waiting', 'mine', 'in_progress', 'closed', 'all'];
$view = (string) ($_GET['view'] ?? 'waiting');
if (!in_array($view, $allowedViews, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_session_view']);
    exit;
}

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../includes/admin_chat_repository.php';

admin_chat_json(admin_chat_sessions_response($conn, $view, (int) $_SESSION['admin_id']));
