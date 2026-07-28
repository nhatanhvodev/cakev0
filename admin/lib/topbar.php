<?php

require_once __DIR__ . '/revenue_report.php';

if (!function_exists('admin_topbar_fetch_all')) {
    function admin_topbar_fetch_all(mysqli $conn, string $sql): array
    {
        $result = $conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}

if (!function_exists('admin_topbar_count')) {
    function admin_topbar_count(mysqli $conn, string $sql): int
    {
        $result = $conn->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }
}

if (!function_exists('admin_topbar_initials')) {
    function admin_topbar_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'AD';
        }
        $parts = preg_split('/\s+/u', $name);
        if (count($parts) >= 2) {
            $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1, 'UTF-8') : substr($parts[0], 0, 1);
            $last = function_exists('mb_substr') ? mb_substr(end($parts), 0, 1, 'UTF-8') : substr(end($parts), 0, 1);
            return strtoupper($first . $last);
        }
        return strtoupper(function_exists('mb_substr') ? mb_substr($name, 0, 2, 'UTF-8') : substr($name, 0, 2));
    }
}

if (!function_exists('admin_topbar_role_label')) {
    function admin_topbar_role_label(string $role): string
    {
        $map = [
            'admin' => 'Quản trị viên',
            'manager' => 'Quản lý',
            'staff' => 'Nhân viên',
        ];
        $key = strtolower(trim($role));
        return $map[$key] ?? 'Quản trị viên';
    }
}

if (!function_exists('admin_topbar_data')) {
    function admin_topbar_data(mysqli $conn): array
    {
        $orders = admin_topbar_fetch_all($conn,
            "SELECT o.id, o.recipient_name, o.total_amount, o.status, u.username
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC
             LIMIT 40"
        );
        $products = admin_topbar_fetch_all($conn,
            "SELECT id, ten_banh, loai, gia FROM banh ORDER BY id DESC LIMIT 40"
        );
        $users = admin_topbar_fetch_all($conn,
            "SELECT id, username, email FROM users ORDER BY created_at DESC LIMIT 40"
        );

        $search = [];
        foreach ($orders as $order) {
            $customer = (string) ($order['username'] ?: $order['recipient_name']);
            $search[] = [
                'type' => 'Đơn hàng',
                'icon' => 'bi-receipt',
                'title' => 'Đơn #' . (int) $order['id'],
                'meta' => $customer . ' · ' . number_format((float) $order['total_amount'], 0, ',', '.') . 'đ · ' . admin_order_status_label((string) $order['status']),
                'href' => 'index.php?tab=orders',
                'keywords' => implode(' ', ['order', $order['id'], $customer, $order['status']]),
            ];
        }
        foreach ($products as $product) {
            $search[] = [
                'type' => 'Sản phẩm',
                'icon' => 'bi-box-seam',
                'title' => (string) $product['ten_banh'],
                'meta' => (string) $product['loai'] . ' · ' . number_format((float) $product['gia'], 0, ',', '.') . 'đ',
                'href' => 'index.php?tab=products',
                'keywords' => implode(' ', ['product', $product['id'], $product['ten_banh'], $product['loai']]),
            ];
        }
        foreach ($users as $user) {
            $search[] = [
                'type' => 'Khách hàng',
                'icon' => 'bi-person',
                'title' => (string) $user['username'],
                'meta' => (string) $user['email'],
                'href' => 'index.php?tab=users',
                'keywords' => implode(' ', ['user', $user['id'], $user['username'], $user['email']]),
            ];
        }

        $notifications = [
            [
                'label' => 'Đơn hàng chờ xử lý',
                'count' => admin_topbar_count($conn, "SELECT COUNT(*) c FROM orders WHERE LOWER(status) IN ('pending','cod_not_deposited')"),
                'href' => 'index.php?tab=orders',
                'icon' => 'bi-cart-check',
            ],
            [
                'label' => 'Liên hệ đang chờ',
                'count' => admin_topbar_count($conn, "SELECT COUNT(*) c FROM contact_requests WHERE status = 'pending'"),
                'href' => 'index.php?tab=contacts',
                'icon' => 'bi-envelope',
            ],
            [
                'label' => 'Yêu cầu mật khẩu',
                'count' => admin_topbar_count($conn, "SELECT COUNT(*) c FROM password_reset_requests WHERE status = 'pending'"),
                'href' => 'index.php?tab=password-requests',
                'icon' => 'bi-key',
            ],
            [
                'label' => 'Hội thoại cần nhận',
                'count' => admin_topbar_count($conn, "SELECT COUNT(*) c FROM chat_sessions WHERE status IN ('open','handoff')"),
                'href' => 'index.php?tab=chat',
                'icon' => 'bi-chat-dots',
            ],
        ];

        $totalNotifications = array_sum(array_column($notifications, 'count'));
        $adminName = (string) ($_SESSION['username'] ?? 'Admin');

        return [
            'search' => $search,
            'notifications' => $notifications,
            'notification_count' => $totalNotifications,
            'admin_name' => $adminName,
            'admin_role' => admin_topbar_role_label((string) ($_SESSION['role'] ?? 'admin')),
            'admin_initials' => admin_topbar_initials($adminName),
        ];
    }
}
