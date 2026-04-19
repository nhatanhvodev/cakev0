<?php

require_once __DIR__ . '/bootstrap.php';

$host = (string) env_value('DB_HOST', '127.0.0.1');
$user = (string) env_value('DB_USER', 'root');
$pass = (string) env_value('DB_PASS', '');
$db = (string) env_value('DB_NAME', 'banh_store');
$port = (int) env_value('DB_PORT', '3306');
$charset = (string) env_value('DB_CHARSET', 'utf8mb4');

if ($port <= 0) {
    $port = 3306;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    $message = 'Kết nối cơ sở dữ liệu thất bại.';
    if (env_bool('APP_DEBUG', false)) {
        $message .= ' ' . $conn->connect_error;
    }
    die($message);
}

$conn->set_charset($charset);
?>