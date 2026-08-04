<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

assert_true(function_exists('sepay_process_webhook'), 'sepay_process_webhook ton tai');

ensureSepayInfrastructure($conn);

$cleanupOrderIds = [];
$cleanupUserId = 0;
register_shutdown_function(function () use ($conn, &$cleanupOrderIds, &$cleanupUserId): void {
    foreach (array_unique(array_filter($cleanupOrderIds)) as $orderId) {
        $stmt = $conn->prepare('DELETE FROM sepay_transactions WHERE order_id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
    }

    if ($cleanupUserId > 0) {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $cleanupUserId);
        $stmt->execute();
        $stmt->close();
    }
});

$username = 'sepay_webhook_' . bin2hex(random_bytes(4));
$email = $username . '@invalid';
$password = password_hash('SePayTest123!', PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users(username, password, email) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $username, $password, $email);
$stmt->execute();
$cleanupUserId = (int) $conn->insert_id;
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, invoice_email_sent_at, created_at)
     VALUES (?, 'Test', '0900000000', 'Dia chi test', '', 'SePay', 250000, NULL, 0, 'pending', NOW(), NOW())"
);
$stmt->bind_param('i', $cleanupUserId);
$stmt->execute();
$cleanupOrderId = (int) $conn->insert_id;
$cleanupOrderIds[] = $cleanupOrderId;
$stmt->close();

$cfg = ['api_key' => 'K123', 'account' => '0123', 'bank' => 'MBBank', 'name' => 'GAU'];
$base = [
    'id' => 'TXN-' . $cleanupOrderId,
    'transferType' => 'in',
    'transferAmount' => 250000,
    'content' => 'DH' . $cleanupOrderId,
    'code' => null,
];

$r = sepay_process_webhook($conn, $base, 'Apikey WRONG', $cfg);
assert_same(401, $r['code'], 'sai key -> 401');

$out = $base;
$out['id'] .= '-OUT';
$out['transferType'] = 'out';
$r = sepay_process_webhook($conn, $out, 'Apikey K123', $cfg);
assert_same(200, $r['code'], 'out -> 200');
assert_same('not_incoming', $r['body']['skipped'], 'out -> skip');

$low = $base;
$low['id'] .= '-LOW';
$low['transferAmount'] = 100000;
$r = sepay_process_webhook($conn, $low, 'Apikey K123', $cfg);
assert_same('amount_mismatch', $r['body']['skipped'], 'thieu tien -> skip');
assert_same('pending', sepay_order_status($conn, $cleanupOrderId, $cleanupUserId), 'van pending');

$stmt = $conn->prepare(
    "INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, invoice_email_sent_at, created_at)
     VALUES (?, 'Test COD', '0900000000', 'Dia chi test', '', 'Tiền mặt', 250000, NULL, 0, 'pending', NOW(), NOW())"
);
$stmt->bind_param('i', $cleanupUserId);
$stmt->execute();
$codOrderId = (int) $conn->insert_id;
$cleanupOrderIds[] = $codOrderId;
$stmt->close();

$cod = $base;
$cod['id'] = 'TXN-COD-' . $codOrderId;
$cod['content'] = 'DH' . $codOrderId;
$r = sepay_process_webhook($conn, $cod, 'Apikey K123', $cfg);
assert_same('not_sepay_order', $r['body']['skipped'], 'COD khong duoc SePay mark paid');
assert_same('pending', sepay_order_status($conn, $codOrderId, $cleanupUserId), 'COD van pending');

$stmt = $conn->prepare(
    "INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, invoice_email_sent_at, created_at)
     VALUES (?, 'Test Expired', '0900000000', 'Dia chi test', '', 'SePay', 250000, NULL, 0, 'pending', NOW(), DATE_SUB(NOW(), INTERVAL 16 MINUTE))"
);
$stmt->bind_param('i', $cleanupUserId);
$stmt->execute();
$expiredOrderId = (int) $conn->insert_id;
$cleanupOrderIds[] = $expiredOrderId;
$stmt->close();

$expired = $base;
$expired['id'] = 'TXN-EXPIRED-' . $expiredOrderId;
$expired['content'] = 'DH' . $expiredOrderId;
$r = sepay_process_webhook($conn, $expired, 'Apikey K123', $cfg);
assert_same('expired', $r['body']['skipped'], 'QR qua 15 phut -> skip');
assert_same('pending', sepay_order_status($conn, $expiredOrderId, $cleanupUserId), 'expired van pending');

$r = sepay_process_webhook($conn, $base, 'Apikey K123', $cfg);
assert_same(200, $r['code'], 'hop le -> 200');
assert_same(true, $r['body']['success'], 'success');
assert_same($cleanupOrderId, $r['body']['order_id'], 'tra order_id');
assert_same('paid', sepay_order_status($conn, $cleanupOrderId, $cleanupUserId), 'da paid');

$r = sepay_process_webhook($conn, $base, 'Apikey K123', $cfg);
assert_same('duplicate', $r['body']['skipped'], 'trung id -> skip');

foreach (array_unique($cleanupOrderIds) as $orderId) {
    $stmt = $conn->prepare('DELETE FROM sepay_transactions WHERE order_id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();
}
$cleanupOrderIds = [];

$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $cleanupUserId);
$stmt->execute();
$stmt->close();
$cleanupUserId = 0;

echo "sepay_webhook_test ... ok\n";
