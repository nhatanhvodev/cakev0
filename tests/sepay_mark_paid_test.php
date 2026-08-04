<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

ensureSepayInfrastructure($conn);

$username = 'sepay_test_' . bin2hex(random_bytes(4));
$email = $username . '@invalid';
$password = password_hash('SePayTest123!', PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users(username, password, email) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $username, $password, $email);
$stmt->execute();
$userId = (int) $conn->insert_id;
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, invoice_email_sent_at, created_at)
     VALUES (?, 'Test', '0900000000', 'Dia chi test', '', 'SePay', 250000, NULL, 0, 'pending', NOW(), NOW())"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$orderId = (int) $conn->insert_id;
$stmt->close();

$r1 = markOrderPaid($conn, $orderId);
assert_same(true, $r1['changed'], 'lan 1 chuyen paid');
assert_same('pending', $r1['previous'], 'previous = pending');

$status = sepay_order_status($conn, $orderId, $userId);
assert_same('paid', $status, 'trang thai paid, dung chu don');

$r2 = markOrderPaid($conn, $orderId);
assert_same(false, $r2['changed'], 'lan 2 khong doi');
assert_same('paid', $r2['previous'], 'previous lan 2 = paid');

assert_same(null, sepay_order_status($conn, $orderId, $userId + 99999), 'sai chu don -> null');

$stmt = $conn->prepare('DELETE FROM sepay_transactions WHERE order_id = ?');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
$stmt->bind_param('i', $orderId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

echo "sepay_mark_paid_test ... ok\n";
