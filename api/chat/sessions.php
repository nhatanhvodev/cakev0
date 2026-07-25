<?php
// Filtered admin session-list proxy.
// api/chat/sessions.php
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

$query = http_build_query([
    'view' => $view,
    'admin_id' => (int) $_SESSION['admin_id'],
]);
$url = chat_ai_service_url() . '/admin/sessions?' . $query;

$headers = ['Content-Type: application/json'];
$bypass = chat_admin_bypass_header();
if ($bypass !== null) {
    $headers[] = $bypass;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
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
