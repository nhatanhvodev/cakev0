<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/registration_helpers.php';

$url = build_registration_verification_url('abc123');
assert_true(str_contains($url, 'verify-registration.php?token=abc123'), 'verification URL should include token');
assert_true(str_starts_with($url, 'http://') || str_starts_with($url, 'https://'), 'verification URL should be absolute');

$mail = build_registration_verification_mail('thanhnhan', 'https://example.test/verify?token=abc123');
assert_same('Xác thực tài khoản Gấu Bakery', $mail['subject'], 'mail subject should match');
assert_true(str_contains($mail['body'], 'thanhnhan'), 'mail body should include username');
assert_true(str_contains($mail['body'], 'https://example.test/verify?token=abc123'), 'mail body should include link');
assert_true(str_contains($mail['body'], '24 giờ'), 'mail body should mention expiry');

echo "registration helpers ok\n";
