<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../config/connect.php';

// Xóa login token nếu có
if (isset($_SESSION['user_id']) && isset($_COOKIE['login_token'])) {
    $token = $_COOKIE['login_token'];
    $query = 'DELETE FROM login_tokens WHERE token = ?';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
    setcookie('login_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// Ghi log đăng xuất
if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
    $logout_time = date('Y-m-d H:i:s');
    $ip_address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $query = "INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, ?, ?, 'logout')";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('iss', $user_id, $logout_time, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// Xóa tất cả biến session
session_unset();
session_destroy();

// Xóa cookie session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

header('Location: ' . base_url('index.php?toast=logout'));
exit;
?>