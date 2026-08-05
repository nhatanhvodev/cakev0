<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../includes/auth_bridge.php';

// User thuong, co custom username claim
$id = auth0_extract_identity([
    'sub' => 'auth0|abc123',
    'email' => 'User@Example.COM',
    'https://gaubakery.app/username' => 'nhatanh',
    'https://gaubakery.app/role' => 'user',
]);
assert_same('auth0|abc123', $id['auth0_id'], 'sub');
assert_same('user@example.com', $id['email'], 'email lowercase');
assert_same('nhatanh', $id['username'], 'username tu custom claim');
assert_same('user', $id['role'], 'role user');

// Admin, khong co username claim -> fallback phan truoc @
$admin = auth0_extract_identity([
    'sub' => 'auth0|adminsub',
    'email' => 'admin@gau.vn',
    'https://gaubakery.app/role' => 'admin',
]);
assert_same('admin', $admin['role'], 'role admin');
assert_same('admin', $admin['username'], 'username fallback local-part');

// Role la la -> ep ve user
$weird = auth0_extract_identity([
    'sub' => 'google-oauth2|9',
    'email' => 'g@gmail.com',
    'https://gaubakery.app/role' => 'superuser',
]);
assert_same('user', $weird['role'], 'role la ep ve user');

echo "auth_bridge_identity_test ... ok\n";
