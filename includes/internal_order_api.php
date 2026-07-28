<?php
// includes/internal_order_api.php
require_once __DIR__ . '/notifications.php';

function internal_api_verify_signature(string $body, ?string $signature, string $secret): bool
{
    if ($signature === null || $signature === '' || $secret === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
}

function internal_order_validate(array $p): ?string
{
    if (empty($p['user_id']) || (int) $p['user_id'] <= 0) return 'missing_user';
    if (empty($p['items']) || !is_array($p['items'])) return 'invalid_items';
    foreach ($p['items'] as $item) {
        $qty = (int) ($item['quantity'] ?? 0);
        if (empty($item['banh_id']) || $qty < 1 || $qty > 20) return 'invalid_quantity';
    }
    if (trim((string) ($p['recipient_name'] ?? '')) === '') return 'missing_recipient';
    if (!preg_match('/^(0|\+84)\d{8,10}$/', (string) ($p['phone'] ?? ''))) return 'invalid_phone';
    if (trim((string) ($p['address'] ?? '')) === '') return 'missing_address';
    return null;
}

function create_order_internal(mysqli $conn, array $p): array
{
    if (($err = internal_order_validate($p)) !== null) {
        return ['error' => 'validation', 'reason' => $err];
    }
    $userId = (int) $p['user_id'];
    $stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) { $stmt->close(); return ['error' => 'validation', 'reason' => 'user_not_found']; }
    $stmt->close();

    $conn->begin_transaction();
    try {
        $total = 0.0;
        $resolved = [];
        foreach ($p['items'] as $item) {
            $banhId = (int) $item['banh_id'];
            $qty = (int) $item['quantity'];
            $stmt = $conn->prepare('SELECT id, gia, stock FROM banh WHERE id = ? AND is_hidden = 0 FOR UPDATE');
            $stmt->bind_param('i', $banhId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $conn->rollback();
                return ['error' => 'validation', 'reason' => 'product_not_found:' . $banhId];
            }
            if ((int) $row['stock'] < $qty) {
                $conn->rollback();
                return ['error' => 'validation', 'reason' => 'out_of_stock:' . $banhId];
            }
            $total += ((float) $row['gia']) * $qty;
            $resolved[] = ['banh_id' => $banhId, 'quantity' => $qty, 'price' => (float) $row['gia']];
        }

        $name = trim((string) $p['recipient_name']);
        $phone = (string) $p['phone'];
        $address = trim((string) $p['address']);
        $note = trim((string) ($p['note'] ?? 'Đặt qua chat'));
        $method = 'Tiền mặt';
        $status = 'pending';
        $stmt = $conn->prepare(
            'INSERT INTO orders (user_id, recipient_name, phone, address, note, payment_method, total_amount, status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isssssds', $userId, $name, $phone, $address, $note, $method, $total, $status);
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        $insItem = $conn->prepare('INSERT INTO order_items (order_id, banh_id, quantity, price) VALUES (?, ?, ?, ?)');
        $decStock = $conn->prepare('UPDATE banh SET stock = stock - ? WHERE id = ? AND stock >= ?');
        foreach ($resolved as $r) {
            $insItem->bind_param('iiid', $orderId, $r['banh_id'], $r['quantity'], $r['price']);
            $insItem->execute();
            $decStock->bind_param('iii', $r['quantity'], $r['banh_id'], $r['quantity']);
            $decStock->execute();
            if ($decStock->affected_rows !== 1) {
                $insItem->close();
                $decStock->close();
                $conn->rollback();
                return ['error' => 'validation', 'reason' => 'out_of_stock:' . $r['banh_id']];
            }
        }
        $insItem->close();
        $decStock->close();
        $conn->commit();
        notifyOrderCreated($conn, $userId, (int) $orderId, $status, (float) $total);
        return ['order_id' => $orderId, 'total_amount' => $total, 'status' => $status];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Internal order API error: ' . $e->getMessage());
        return ['error' => 'server', 'reason' => 'insert_failed'];
    }
}
