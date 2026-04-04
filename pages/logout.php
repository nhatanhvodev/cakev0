<?php
session_start();

// Kết nối cơ sở dữ liệu
$connect_file = 'connect.php';
if (file_exists($connect_file)) {
    require_once $connect_file;
} else {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "banh_store";
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8");
    if ($conn->connect_error) {
        die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
    }
}

// Xóa login token nếu có
if (isset($_SESSION['user_id']) && isset($_COOKIE['login_token'])) {
    $token = $_COOKIE['login_token'];
    $query = "DELETE FROM login_tokens WHERE token = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    setcookie('login_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

// Ghi log đăng xuất
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $logout_time = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, ?, ?, 'logout')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $logout_time, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Đóng kết nối cơ sở dữ liệu
$conn->close();

// Xóa tất cả biến session
session_unset();
// Hủy session trên server
session_destroy();

// Xóa cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Chuyển hướng đến index.php kèm toast
header("Location: /Cake/index.php?toast=logout");
exit;
?>