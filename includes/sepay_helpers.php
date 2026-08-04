<?php

if (!function_exists('sepay_config')) {
    function sepay_config(): array
    {
        return [
            'api_key' => (string) env_value('SEPAY_WEBHOOK_API_KEY', ''),
            'account' => (string) env_value('SEPAY_ACCOUNT_NUMBER', ''),
            'bank'    => (string) env_value('SEPAY_BANK_CODE', ''),
            'name'    => (string) env_value('SEPAY_ACCOUNT_NAME', ''),
        ];
    }
}

if (!function_exists('sepay_payment_content')) {
    function sepay_payment_content(int $orderId): string
    {
        return 'DH' . $orderId;
    }
}

if (!function_exists('sepay_extract_order_id')) {
    function sepay_extract_order_id(string $content, ?string $code = null): ?int
    {
        foreach ([$code, $content] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (preg_match('/DH0*(\d+)/i', $candidate, $m)) {
                return (int) $m[1];
            }
        }
        return null;
    }
}

if (!function_exists('sepay_verify_api_key')) {
    function sepay_verify_api_key(?string $authHeader, string $expectedKey): bool
    {
        if ($expectedKey === '' || $authHeader === null) {
            return false;
        }
        $prefix = 'Apikey ';
        if (strncmp($authHeader, $prefix, strlen($prefix)) !== 0) {
            return false;
        }
        $provided = substr($authHeader, strlen($prefix));
        return hash_equals($expectedKey, $provided);
    }
}

if (!function_exists('sepay_build_qr_url')) {
    function sepay_build_qr_url(array $cfg, int $orderId, int $amount): string
    {
        $params = http_build_query([
            'acc'    => $cfg['account'] ?? '',
            'bank'   => $cfg['bank'] ?? '',
            'amount' => $amount,
            'des'    => sepay_payment_content($orderId),
        ]);
        return 'https://qr.sepay.vn/img?' . $params;
    }
}

if (!function_exists('ensureSepayInfrastructure')) {
    function ensureSepayInfrastructure(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS sepay_transactions (
                sepay_id   VARCHAR(50) PRIMARY KEY,
                order_id   INT NOT NULL,
                amount     BIGINT NOT NULL,
                content    VARCHAR(255) NULL,
                raw        TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('sepay_order_status')) {
    function sepay_order_status(mysqli $conn, int $orderId, int $userId): ?string
    {
        $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (string) $row['status'] : null;
    }
}

if (!function_exists('markOrderPaid')) {
    function markOrderPaid(mysqli $conn, int $orderId): array
    {
        require_once __DIR__ . '/../config/coupons.php';
        require_once __DIR__ . '/notifications.php';
        require_once __DIR__ . '/invoice_mailer.php';

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT user_id, coupon_code, status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                $conn->rollback();
                return ['changed' => false, 'previous' => ''];
            }

            $previousStatus = (string) $order['status'];
            if ($previousStatus === 'paid') {
                $conn->rollback();
                return ['changed' => false, 'previous' => 'paid'];
            }

            $userId = (int) ($order['user_id'] ?? 0);
            $couponCode = (string) ($order['coupon_code'] ?? '');

            $stmt = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $stmt->close();

            if ($userId > 0) {
                $stmt = $conn->prepare("SELECT banh_id, quantity FROM order_items WHERE order_id = ?");
                $stmt->bind_param('i', $orderId);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $updateCart = $conn->prepare("UPDATE cart SET quantity = quantity - ? WHERE user_id = ? AND banh_id = ?");
                $deleteEmpty = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND banh_id = ? AND quantity <= 0");

                foreach ($items as $item) {
                    $quantity = (int) $item['quantity'];
                    $banhId = (int) $item['banh_id'];

                    $updateCart->bind_param('iii', $quantity, $userId, $banhId);
                    $updateCart->execute();

                    $deleteEmpty->bind_param('ii', $userId, $banhId);
                    $deleteEmpty->execute();
                }

                $updateCart->close();
                $deleteEmpty->close();
            }

            if ($couponCode !== '') {
                incrementCouponUsage($conn, $couponCode);
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('SePay markOrderPaid error: ' . $e->getMessage());
            return ['changed' => false, 'previous' => ''];
        }

        notifyOrderStatusChanged($conn, $userId, $orderId, $previousStatus, 'paid');
        if (!send_order_invoice_email($conn, $orderId)) {
            error_log('SePay: gui hoa don that bai cho don #' . $orderId);
        }

        return ['changed' => true, 'previous' => $previousStatus];
    }
}

if (!function_exists('sepay_process_webhook')) {
    function sepay_process_webhook(mysqli $conn, array $payload, ?string $authHeader, array $cfg): array
    {
        if (!sepay_verify_api_key($authHeader, (string) ($cfg['api_key'] ?? ''))) {
            return ['code' => 401, 'body' => ['error' => 'unauthorized']];
        }

        $transferType = (string) ($payload['transferType'] ?? '');
        if ($transferType !== 'in') {
            return ['code' => 200, 'body' => ['skipped' => 'not_incoming']];
        }

        $content = (string) ($payload['content'] ?? '');
        $code = isset($payload['code']) ? (string) $payload['code'] : null;
        $orderId = sepay_extract_order_id($content, $code);
        if ($orderId === null) {
            return ['code' => 200, 'body' => ['skipped' => 'no_order']];
        }

        $sepayId = trim((string) ($payload['id'] ?? ''));
        if ($sepayId === '') {
            return ['code' => 200, 'body' => ['skipped' => 'missing_id']];
        }

        $amount = (int) ($payload['transferAmount'] ?? 0);

        ensureSepayInfrastructure($conn);

        $stmt = $conn->prepare("SELECT sepay_id FROM sepay_transactions WHERE sepay_id = ? LIMIT 1");
        $stmt->bind_param('s', $sepayId);
        $stmt->execute();
        $existingTransaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existingTransaction) {
            return ['code' => 200, 'body' => ['skipped' => 'duplicate']];
        }

        $stmt = $conn->prepare("SELECT total_amount, payment_method, status, created_at FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return ['code' => 200, 'body' => ['skipped' => 'order_not_found']];
        }

        if ((string) ($order['payment_method'] ?? '') !== 'SePay') {
            return ['code' => 200, 'body' => ['skipped' => 'not_sepay_order']];
        }

        $status = (string) ($order['status'] ?? '');
        if ($status === 'paid') {
            return ['code' => 200, 'body' => ['skipped' => 'already_paid']];
        }
        if ($status !== 'pending') {
            return ['code' => 200, 'body' => ['skipped' => 'status_not_payable']];
        }

        $createdAt = strtotime((string) ($order['created_at'] ?? ''));
        if ($createdAt > 0 && $createdAt + 15 * 60 < time()) {
            return ['code' => 200, 'body' => ['skipped' => 'expired']];
        }

        if ($amount < (int) round((float) $order['total_amount'])) {
            return ['code' => 200, 'body' => ['skipped' => 'amount_mismatch']];
        }

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            $raw = '';
        }

        $stmt = $conn->prepare(
            "INSERT IGNORE INTO sepay_transactions(sepay_id, order_id, amount, content, raw)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('siiss', $sepayId, $orderId, $amount, $content, $raw);
        $stmt->execute();
        $inserted = $stmt->affected_rows;
        $stmt->close();

        if ($inserted === 0) {
            return ['code' => 200, 'body' => ['skipped' => 'duplicate']];
        }

        $paid = markOrderPaid($conn, $orderId);
        if (($paid['changed'] ?? false) !== true) {
            $stmt = $conn->prepare("DELETE FROM sepay_transactions WHERE sepay_id = ?");
            $stmt->bind_param('s', $sepayId);
            $stmt->execute();
            $stmt->close();

            if (($paid['previous'] ?? '') === 'paid') {
                return ['code' => 200, 'body' => ['skipped' => 'already_paid']];
            }

            return ['code' => 500, 'body' => ['error' => 'mark_paid_failed']];
        }

        return ['code' => 200, 'body' => ['success' => true, 'order_id' => $orderId]];
    }
}
