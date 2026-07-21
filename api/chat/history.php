<?php
// api/chat/history.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/chat_proxy_helpers.php';

session_start();

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

$query = ['session_id' => $sessionId];
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
    $secret = getenv('INTERNAL_API_SECRET') ?: '';
    $headers[] = 'X-Admin-Bypass: ' . hash_hmac('sha256', 'admin', $secret);
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
