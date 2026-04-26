<?php

require_once __DIR__ . '/config.php';

if (!function_exists('ensureCartCouponInfrastructure')) {
    function ensureCartCouponInfrastructure(mysqli $conn): void
    {
        $createCouponsSql = "CREATE TABLE IF NOT EXISTS cart_coupons (
            id INT(11) NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            discount_percent DECIMAL(5,2) NOT NULL,
            min_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            usage_limit INT(11) DEFAULT NULL,
            used_count INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            starts_at DATE DEFAULT NULL,
            ends_at DATE DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_cart_coupon_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $conn->query($createCouponsSql);

        $couponColumns = [
            'usage_limit' => "ALTER TABLE cart_coupons ADD COLUMN usage_limit INT(11) DEFAULT NULL AFTER min_subtotal",
            'used_count' => "ALTER TABLE cart_coupons ADD COLUMN used_count INT(11) NOT NULL DEFAULT 0 AFTER usage_limit",
            'is_active' => "ALTER TABLE cart_coupons ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER used_count",
            'starts_at' => "ALTER TABLE cart_coupons ADD COLUMN starts_at DATE DEFAULT NULL AFTER is_active",
            'ends_at' => "ALTER TABLE cart_coupons ADD COLUMN ends_at DATE DEFAULT NULL AFTER starts_at",
            'created_at' => "ALTER TABLE cart_coupons ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ends_at",
        ];

        foreach ($couponColumns as $column => $sql) {
            $result = $conn->query("SHOW COLUMNS FROM cart_coupons LIKE '{$column}'");
            if ($result && $result->num_rows === 0) {
                $conn->query($sql);
            }
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        }

        $orderColumns = [
            'coupon_code' => "ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL AFTER payment_method",
            'coupon_discount' => "ALTER TABLE orders ADD COLUMN coupon_discount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER coupon_code",
        ];

        foreach ($orderColumns as $column => $sql) {
            $result = $conn->query("SHOW COLUMNS FROM orders LIKE '{$column}'");
            if ($result && $result->num_rows === 0) {
                $conn->query($sql);
            }
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        }

        seedDefaultCartCoupons($conn);
    }
}

if (!function_exists('seedDefaultCartCoupons')) {
    function seedDefaultCartCoupons(mysqli $conn): void
    {
        $defaultCoupons = [
            ['code' => 'TEST10', 'discount_percent' => 10.0],
            ['code' => 'SAVE15', 'discount_percent' => 15.0],
            ['code' => 'SAVE20', 'discount_percent' => 20.0],
            ['code' => 'VIP25', 'discount_percent' => 25.0],
            ['code' => 'CAKE30', 'discount_percent' => 30.0],
        ];

        $stmt = $conn->prepare(
            "INSERT INTO cart_coupons (code, discount_percent, min_subtotal, usage_limit, used_count, is_active, starts_at, ends_at)
             VALUES (?, ?, 0, NULL, 0, 1, NULL, NULL)
             ON DUPLICATE KEY UPDATE code = code"
        );

        if (!$stmt) {
            return;
        }

        foreach ($defaultCoupons as $coupon) {
            $code = $coupon['code'];
            $discountPercent = $coupon['discount_percent'];
            $stmt->bind_param('sd', $code, $discountPercent);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('normalizeCouponCode')) {
    function normalizeCouponCode(string $couponCode): string
    {
        return strtoupper(trim($couponCode));
    }
}

if (!function_exists('findCartCoupon')) {
    function findCartCoupon(mysqli $conn, string $couponCode, string $today): ?array
    {
        $stmt = $conn->prepare(
            "SELECT id, code, discount_percent, min_subtotal, usage_limit, used_count, starts_at, ends_at
             FROM cart_coupons
             WHERE UPPER(code) = UPPER(?)
             AND is_active = 1
             AND (starts_at IS NULL OR starts_at <= ?)
             AND (ends_at IS NULL OR ends_at >= ?)
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('sss', $couponCode, $today, $today);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('formatCouponDateValue')) {
    function formatCouponDateValue(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }
}

if (!function_exists('parseCouponUsageLimit')) {
    function parseCouponUsageLimit($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;
        return $parsed > 0 ? $parsed : null;
    }
}

if (!function_exists('couponUsageLabel')) {
    function couponUsageLabel(array $coupon): string
    {
        $usedCount = (int) ($coupon['used_count'] ?? 0);
        $usageLimit = isset($coupon['usage_limit']) ? (int) $coupon['usage_limit'] : 0;

        if ($usageLimit <= 0) {
            return $usedCount . ' / Không giới hạn';
        }

        return $usedCount . ' / ' . $usageLimit;
    }
}

if (!function_exists('incrementCouponUsage')) {
    function incrementCouponUsage(mysqli $conn, string $couponCode): void
    {
        $normalizedCode = normalizeCouponCode($couponCode);
        if ($normalizedCode === '') {
            return;
        }

        $stmt = $conn->prepare(
            "UPDATE cart_coupons
             SET used_count = used_count + 1
             WHERE UPPER(code) = UPPER(?)
             AND (usage_limit IS NULL OR usage_limit <= 0 OR used_count < usage_limit)"
        );

        if (!$stmt) {
            return;
        }

        $stmt->bind_param('s', $normalizedCode);
        $stmt->execute();
        $stmt->close();
    }
}
