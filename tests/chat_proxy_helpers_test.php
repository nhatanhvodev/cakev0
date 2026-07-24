<?php
// tests/chat_proxy_helpers_test.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/chat_proxy_helpers.php';

$payload = chat_build_forward_payload(
    ['message' => 'hi', 'session_id' => '5', 'user_id' => '999', 'evil' => 'x'],
    42
);
assert_same('hi', $payload['message'], 'message passthrough');
assert_same(5, $payload['session_id'], 'session_id cast int');
assert_same(42, $payload['user_id'], 'user_id from auth, not from client');
assert_true(!array_key_exists('evil', $payload), 'unknown fields dropped');

$guest = chat_build_forward_payload(['message' => 'hi', 'guest_token' => 'abc'], null);
assert_same('abc', $guest['guest_token'], 'guest token passthrough');
assert_true(!isset($guest['user_id']), 'no user_id for guest');

echo "OK\n";
