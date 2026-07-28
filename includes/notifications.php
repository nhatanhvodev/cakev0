<?php

if (!function_exists('ensureUserNotificationInfrastructure')) {
    function ensureUserNotificationInfrastructure(mysqli $conn): bool
    {
        static $ready = [];
        $key = spl_object_id($conn);
        if (array_key_exists($key, $ready)) {
            return $ready[$key];
        }

        $sql = "CREATE TABLE IF NOT EXISTS user_notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            type VARCHAR(40) NOT NULL,
            title VARCHAR(160) NOT NULL,
            message VARCHAR(255) NOT NULL,
            href VARCHAR(255) DEFAULT NULL,
            icon VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-bell',
            source_type VARCHAR(40) DEFAULT NULL,
            source_id INT(11) DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_notifications_user_read_created (user_id, read_at, created_at),
            KEY idx_user_notifications_source (source_type, source_id),
            CONSTRAINT user_notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $ready[$key] = (bool) $conn->query($sql);
        return $ready[$key];
    }
}

if (!function_exists('notification_base_url')) {
    function notification_base_url(): string
    {
        $base = defined('BASE_URL') ? (string) BASE_URL : '/cakev0/';
        return rtrim($base, '/') . '/';
    }
}

if (!function_exists('notification_order_href')) {
    function notification_order_href(int $orderId): string
    {
        return notification_base_url() . 'pages/order-detail.php?id=' . $orderId;
    }
}

if (!function_exists('notification_vnd')) {
    function notification_vnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' VNĐ';
    }
}

if (!function_exists('notification_substr')) {
    function notification_substr(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}

if (!function_exists('notification_order_status_label')) {
    function notification_order_status_label(string $status): string
    {
        $map = [
            'pending' => 'Chờ xác nhận',
            'paid' => 'Đã thanh toán',
            'approved' => 'Đã xác nhận',
            'confirmed' => 'Đã xác nhận',
            'delivering' => 'Đang giao',
            'delivered' => 'Đã giao',
            'completed' => 'Hoàn tất',
            'failed' => 'Thanh toán lỗi',
            'cancelled' => 'Đã hủy',
            'cod_not_deposited' => 'Chưa đặt cọc',
            'cod_deposited' => 'Đã đặt cọc',
        ];
        $key = strtolower(trim($status));
        return $map[$key] ?? ($status !== '' ? $status : 'Không rõ');
    }
}

if (!function_exists('createUserNotification')) {
    function createUserNotification(
        mysqli $conn,
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $href = null,
        string $icon = 'fa-solid fa-bell',
        ?string $sourceType = null,
        ?int $sourceId = null
    ): bool {
        if ($userId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return false;
        }

        $title = notification_substr(trim($title), 160);
        $message = notification_substr(trim($message), 255);
        $type = substr(trim($type), 0, 40);
        $icon = substr(trim($icon), 0, 60);
        $hrefValue = $href !== null && trim($href) !== '' ? substr(trim($href), 0, 255) : null;
        $sourceTypeValue = $sourceType !== null && trim($sourceType) !== '' ? substr(trim($sourceType), 0, 40) : null;
        $sourceIdValue = $sourceId !== null && $sourceId > 0 ? $sourceId : null;

        if ($title === '' || $message === '' || $type === '') {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO user_notifications
                (user_id, type, title, message, href, icon, source_type, source_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'issssssi',
            $userId,
            $type,
            $title,
            $message,
            $hrefValue,
            $icon,
            $sourceTypeValue,
            $sourceIdValue
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('notifyOrderCreated')) {
    function notifyOrderCreated(mysqli $conn, int $userId, int $orderId, string $status, float $totalAmount): bool
    {
        $label = notification_order_status_label($status);
        $message = 'Gấu Bakery đã ghi nhận đơn ' . notification_vnd($totalAmount) . '. Trạng thái hiện tại: ' . $label . '.';
        if ($status === 'cod_not_deposited') {
            $message = 'Gấu Bakery đã nhận đơn ' . notification_vnd($totalAmount) . '. Vui lòng đặt cọc để shop xác nhận đơn.';
        }

        return createUserNotification(
            $conn,
            $userId,
            'order_created',
            'Đơn #' . $orderId . ' đã được tạo',
            $message,
            notification_order_href($orderId),
            'fa-solid fa-receipt',
            'order',
            $orderId
        );
    }
}

if (!function_exists('notifyOrderStatusChanged')) {
    function notifyOrderStatusChanged(mysqli $conn, int $userId, int $orderId, string $oldStatus, string $newStatus): bool
    {
        $oldKey = strtolower(trim($oldStatus));
        $newKey = strtolower(trim($newStatus));
        if ($userId <= 0 || $orderId <= 0 || $newKey === '' || $oldKey === $newKey) {
            return false;
        }

        $newLabel = notification_order_status_label($newKey);
        $message = 'Trạng thái đơn hàng chuyển từ ' . notification_order_status_label($oldKey) . ' sang ' . $newLabel . '.';

        return createUserNotification(
            $conn,
            $userId,
            'order_status',
            'Đơn #' . $orderId . ': ' . $newLabel,
            $message,
            notification_order_href($orderId),
            'fa-solid fa-truck-fast',
            'order',
            $orderId
        );
    }
}

if (!function_exists('notifyPasswordRequestResult')) {
    function notifyPasswordRequestResult(mysqli $conn, int $userId, int $requestId, string $status): bool
    {
        $approved = $status === 'approved';
        return createUserNotification(
            $conn,
            $userId,
            'password_request',
            $approved ? 'Mật khẩu đã được cập nhật' : 'Yêu cầu đổi mật khẩu bị từ chối',
            $approved
                ? 'Admin đã duyệt yêu cầu đổi mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.'
                : 'Admin đã từ chối yêu cầu đổi mật khẩu. Vui lòng gửi lại yêu cầu nếu cần hỗ trợ.',
            notification_base_url() . 'pages/account.php',
            'fa-solid fa-key',
            'password_reset_request',
            $requestId
        );
    }
}

if (!function_exists('deleteOrderNotifications')) {
    function deleteOrderNotifications(mysqli $conn, int $orderId): void
    {
        if ($orderId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return;
        }
        $stmt = $conn->prepare("DELETE FROM user_notifications WHERE source_type = 'order' AND source_id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('fetchUserNotifications')) {
    function fetchUserNotifications(mysqli $conn, int $userId, int $limit = 8): array
    {
        if ($userId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return [];
        }

        $limit = max(1, min($limit, 20));
        $stmt = $conn->prepare(
            "SELECT id, type, title, message, href, icon, read_at, created_at
             FROM user_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['is_read'] = !empty($row['read_at']);
            $row['created_label'] = date('d/m H:i', strtotime((string) $row['created_at']));
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('countUnreadUserNotifications')) {
    function countUnreadUserNotifications(mysqli $conn, int $userId): int
    {
        if ($userId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM user_notifications WHERE user_id = ? AND read_at IS NULL");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('markUserNotificationRead')) {
    function markUserNotificationRead(mysqli $conn, int $userId, int $notificationId): bool
    {
        if ($userId <= 0 || $notificationId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return false;
        }

        $stmt = $conn->prepare(
            "UPDATE user_notifications
             SET read_at = COALESCE(read_at, NOW())
             WHERE id = ? AND user_id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $notificationId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('markAllUserNotificationsRead')) {
    function markAllUserNotificationsRead(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0 || !ensureUserNotificationInfrastructure($conn)) {
            return false;
        }

        $stmt = $conn->prepare(
            "UPDATE user_notifications
             SET read_at = COALESCE(read_at, NOW())
             WHERE user_id = ? AND read_at IS NULL"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
