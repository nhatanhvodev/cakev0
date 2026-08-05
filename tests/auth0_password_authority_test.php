<?php
require __DIR__ . '/bootstrap.php';

$account = file_get_contents(__DIR__ . '/../pages/account.php');
assert_true(str_contains($account, "base_url('pages/forgot-password.php')"), 'account change_password chuyen qua Auth0 reset');
assert_true(str_contains($account, 'forgot-password.php'), 'account UI co link reset qua Auth0');
assert_true(!str_contains($account, 'password_verify('), 'account khong verify password local');
assert_true(!str_contains($account, 'password_reset_requests'), 'account khong tao request doi mat khau local');

$passwordHandler = file_get_contents(__DIR__ . '/../admin/handlers/password_requests.php');
assert_true(str_contains($passwordHandler, 'Auth0'), 'handler admin thong bao Auth0 la authority');
assert_true(!str_contains($passwordHandler, 'UPDATE users SET password'), 'admin khong update users.password');
assert_true(!str_contains($passwordHandler, 'send_custom_mail'), 'admin khong gui mail doi password local');

$passwordTab = file_get_contents(__DIR__ . '/../admin/views/tabs/password-requests.php');
assert_true(!str_contains($passwordTab, 'update_password_request_status'), 'tab admin khong hien action duyet password local');
assert_true(str_contains($passwordTab, 'Auth0'), 'tab admin noi ro password qua Auth0');

echo "auth0_password_authority_test ... ok\n";
