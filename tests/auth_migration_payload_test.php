<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../scripts/migrate_users_to_auth0.php';

$row = [
    'id' => 7,
    'email' => 'u@test.vn',
    'username' => 'nhatanh',
    'password' => '$2y$10$abcdefghijklmnopqrstuv',
    'phone' => '0900',
];

$p = build_import_payload($row, false);
assert_same('u@test.vn', $p['email'], 'email');
assert_true($p['email_verified'] === true, 'email_verified');
assert_same('cakev0_user_7', $p['username'], 'root username bat buoc cho connection username');
assert_same('cakev0-user-7', $p['user_id'], 'user_id import on dinh');
assert_same('bcrypt', $p['custom_password_hash']['algorithm'], 'algo bcrypt');
assert_same('$2y$10$abcdefghijklmnopqrstuv', $p['custom_password_hash']['hash']['value'], 'hash nguyen ven');
assert_same('utf8', $p['custom_password_hash']['hash']['encoding'], 'hash encoding utf8');
assert_same('nhatanh', $p['user_metadata']['username'], 'username metadata');
assert_same('0900', $p['user_metadata']['phone'], 'phone metadata');
assert_true(!isset($p['app_metadata']), 'user thuong khong co app_metadata role');

$pa = build_import_payload($row, true);
assert_same('admin', $pa['app_metadata']['role'], 'admin co role metadata');
assert_same('cakev0-admin-7', $pa['user_id'], 'admin user_id import on dinh');
assert_same('cakev0_admin_7', $pa['username'], 'admin root username bat buoc');

$script = file_get_contents(__DIR__ . '/../scripts/migrate_users_to_auth0.php');
assert_true(!str_contains($script, 'UPDATE users SET auth0_id'), 'khong update auth0_id truoc khi import job completed');
assert_true(str_contains($script, "'upsert' => 'false'"), 'khong upsert de tranh de metadata khi trung email');

echo "auth_migration_payload_test ... ok\n";
