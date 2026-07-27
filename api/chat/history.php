<?php
// Cursor-aware chat history proxy.
// api/chat/history.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_proxy_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'session_id must be a positive integer']);
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min($limit, 100));
$beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : null;
$afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : null;

if (($beforeId !== null && $beforeId <= 0) || ($afterId !== null && $afterId <= 0)) {
    http_response_code(422);
    echo json_encode(['error' => 'message cursors must be positive integers']);
    exit;
}
if ($beforeId !== null && $afterId !== null) {
    http_response_code(422);
    echo json_encode(['error' => 'use only one message cursor']);
    exit;
}

if (!empty($_SESSION['admin_logged_in'])) {
    require_once __DIR__ . '/../../config/connect.php';
    require_once __DIR__ . '/../../includes/admin_chat_repository.php';
    admin_chat_json(admin_chat_history_response($conn, $sessionId, $limit, $beforeId, $afterId));
}

$query = [
    'session_id' => $sessionId,
    'limit' => $limit,
];
if ($beforeId !== null) {
    $query['before_id'] = $beforeId;
}
if ($afterId !== null) {
    $query['after_id'] = $afterId;
}

$adminBypass = false;
if (!empty($_SESSION['admin_logged_in'])) {
    $adminBypass = true;
} elseif (isset($_SESSION['user_id'])) {
    $query['user_id'] = (int) $_SESSION['user_id'];
} elseif (!empty($_GET['guest_token'])) {
    $query['guest_token'] = substr((string) $_GET['guest_token'], 0, 64);
}

$url = chat_ai_service_url() . '/chat/history?' . http_build_query($query);
$headers = ['Content-Type: application/json'];
if ($adminBypass) {
    $bypass = chat_admin_bypass_header();
    if ($bypass !== null) {
        $headers[] = $bypass;
    }
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
