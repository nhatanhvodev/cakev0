<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth0.php';
require_once __DIR__ . '/../../config/connect.php';

if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'logout')");
    if ($stmt) {
        $stmt->bind_param('is', $uid, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

$auth0 = auth0_client();
$returnTo = env_value('AUTH0_LOGOUT_RETURN_URL', null) ?: absolute_url('index.php');

session_unset();
session_destroy();

header('Location: ' . $auth0->logout($returnTo));
exit;
