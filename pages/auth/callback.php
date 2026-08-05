<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../includes/auth_bridge.php';
require_once __DIR__ . '/../../config/connect.php';

$auth0 = auth0_client();

try {
    $auth0->exchange();
} catch (Throwable $e) {
    header('Location: ' . base_url('index.php?toast=auth_error'));
    exit;
}

$credentials = $auth0->getCredentials();
if ($credentials === null || $credentials->user === null) {
    header('Location: ' . base_url('index.php?toast=auth_error'));
    exit;
}

$user = sync_session_from_auth0($conn, (array) $credentials->user);

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
if ($stmt) {
    $uid = (int) $user['id'];
    $stmt->bind_param('is', $uid, $ip);
    $stmt->execute();
    $stmt->close();
}

$fallback = $user['role'] === 'admin' ? base_url('admin/index.php') : base_url('index.php');
$target = safe_redirect_target($_SESSION['auth_return_to'] ?? null, $fallback);
if ($user['role'] === 'admin') {
    $target = base_url('admin/index.php');
}
unset($_SESSION['auth_return_to']);

header('Location: ' . $target);
exit;
