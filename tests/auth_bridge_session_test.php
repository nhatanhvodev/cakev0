<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/auth_bridge.php';

$_SESSION = [];
apply_session_for_user(['id' => 7, 'username' => 'nhatanh', 'role' => 'user']);
assert_same(7, $_SESSION['user_id'], 'user_id set');
assert_same('nhatanh', $_SESSION['username'], 'username set');
assert_same('user', $_SESSION['role'], 'role set');
assert_true(!isset($_SESSION['admin_logged_in']), 'user thuong khong co co admin');

$_SESSION = [];
apply_session_for_user(['id' => 1, 'username' => 'admin', 'role' => 'admin']);
assert_true($_SESSION['admin_logged_in'] === true, 'admin co co admin_logged_in');
assert_same(1, $_SESSION['admin_id'], 'admin_id set');

assert_same('/cakev0/pages/account.php', safe_redirect_target('/cakev0/pages/account.php', '/cakev0/index.php'), 'path noi bo ok');
assert_same('/cakev0/index.php', safe_redirect_target('https://evil.com', '/cakev0/index.php'), 'chan URL ngoai');
assert_same('/cakev0/index.php', safe_redirect_target('//evil.com', '/cakev0/index.php'), 'chan protocol-relative');
assert_same('/cakev0/index.php', safe_redirect_target(null, '/cakev0/index.php'), 'null -> fallback');

assert_same('provider_access_denied', auth0_callback_error_reason(new RuntimeException('Missing code'), ['error' => 'access_denied']), 'provider error uu tien');
assert_same('invalid_state', auth0_callback_error_reason(new RuntimeException('Invalid state')), 'phan loai invalid state');
assert_same('failed_code_exchange', auth0_callback_error_reason(new RuntimeException('Code exchange was unsuccessful; network error resulted in unfulfilled request')), 'phan loai code exchange');
assert_same('Bad request detail', auth0_callback_error_detail(new RuntimeException('Ignored'), ['error_description' => "Bad\r\nrequest\t detail"]), 'sanitize provider detail');
assert_same('Fallback message', auth0_callback_error_detail(new RuntimeException('Fallback message')), 'fallback detail tu exception');

echo "auth_bridge_session_test ... ok\n";
