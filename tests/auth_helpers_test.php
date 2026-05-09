<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

assert_same(
    'Mật khẩu phải có ít nhất 12 ký tự.',
    validate_password_strength('Short1!'),
    'reject passwords shorter than 12 characters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ in hoa.',
    validate_password_strength('lowercase123!'),
    'reject passwords without uppercase letters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ thường.',
    validate_password_strength('UPPERCASE123!'),
    'reject passwords without lowercase letters'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 chữ số.',
    validate_password_strength('NoDigitsHere!'),
    'reject passwords without digits'
);

assert_same(
    'Mật khẩu phải có ít nhất 1 ký tự đặc biệt.',
    validate_password_strength('NoSpecial1234'),
    'reject passwords without special characters'
);

assert_same(
    null,
    validate_password_strength('ValidPassword123!'),
    'accept strong passwords'
);

$tokenA = generate_verification_token();
$tokenB = generate_verification_token();

assert_true(strlen($tokenA) === 64, 'generated tokens should be 64 characters long');
assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $tokenA), 'generated tokens should be lowercase hex');
assert_true(strlen($tokenB) === 64, 'generated tokens should be 64 characters long');
assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $tokenB), 'generated tokens should be lowercase hex');
assert_true($tokenA !== $tokenB, 'generated tokens should differ from each other');

echo "auth helpers ok\n";
