<?php
// tests/internal_order_api_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/internal_order_api.php';

$secret = 'test-secret';
$body = '{"user_id": 1}';
$sig = hash_hmac('sha256', $body, $secret);
assert_true(internal_api_verify_signature($body, $sig, $secret), 'valid signature accepted');
assert_true(!internal_api_verify_signature($body, 'sai', $secret), 'invalid signature rejected');
assert_true(!internal_api_verify_signature($body, null, $secret), 'missing signature rejected');

assert_same('missing_user', internal_order_validate(['items' => [['banh_id' => 1, 'quantity' => 1]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'user required');
assert_same('invalid_items', internal_order_validate(['user_id' => 1, 'items' => [], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'items required');
assert_same('invalid_quantity', internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 99]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'quantity cap 20');
assert_same('invalid_phone', internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 1]], 'recipient_name' => 'A', 'phone' => '123', 'address' => 'HN']), 'phone regex');
assert_same(null, internal_order_validate(['user_id' => 1, 'items' => [['banh_id' => 1, 'quantity' => 2]], 'recipient_name' => 'A', 'phone' => '0901234567', 'address' => 'HN']), 'valid payload passes');

echo "OK\n";
