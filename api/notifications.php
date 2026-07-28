<?php

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function notification_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId <= 0) {
    notification_api_json(['error' => 'unauthenticated'], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    notification_api_json([
        'notifications' => fetchUserNotifications($conn, $userId, 10),
        'unread_count' => countUnreadUserNotifications($conn, $userId),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notification_api_json(['error' => 'method'], 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrf = (string) ($payload['csrf_token'] ?? '');
if (empty($_SESSION['notification_csrf']) || !hash_equals((string) $_SESSION['notification_csrf'], $csrf)) {
    notification_api_json(['error' => 'csrf'], 403);
}

$action = (string) ($payload['action'] ?? '');
if ($action === 'mark_read') {
    $notificationId = (int) ($payload['id'] ?? 0);
    markUserNotificationRead($conn, $userId, $notificationId);
} elseif ($action === 'mark_all_read') {
    markAllUserNotificationsRead($conn, $userId);
} else {
    notification_api_json(['error' => 'invalid_action'], 422);
}

notification_api_json([
    'notifications' => fetchUserNotifications($conn, $userId, 10),
    'unread_count' => countUnreadUserNotifications($conn, $userId),
]);
