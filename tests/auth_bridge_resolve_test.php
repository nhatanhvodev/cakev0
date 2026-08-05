<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../includes/auth_bridge.php';

// Don sach tai khoan test
$conn->query("DELETE FROM users WHERE email IN ('link@test.vn','new@test.vn')");

// 1) Tao user cu (chua co auth0_id), khop theo email -> phai link
$hash = password_hash('x', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
$u = 'linkuser'; $e = 'link@test.vn';
$stmt->bind_param('sss', $u, $hash, $e);
$stmt->execute();
$oldId = (int) $conn->insert_id;
$stmt->close();

$row = resolve_local_user($conn, [
    'auth0_id' => 'auth0|link', 'email' => 'link@test.vn',
    'username' => 'linkuser', 'role' => 'user',
]);
assert_same($oldId, $row['id'], 'khop email tra ve dung dong cu');

$check = $conn->query("SELECT auth0_id FROM users WHERE id = $oldId")->fetch_assoc();
assert_same('auth0|link', $check['auth0_id'], 'auth0_id da duoc link');

// 2) Goi lai -> khop theo auth0_id, khong tao trung
$row2 = resolve_local_user($conn, [
    'auth0_id' => 'auth0|link', 'email' => 'link@test.vn',
    'username' => 'linkuser', 'role' => 'user',
]);
assert_same($oldId, $row2['id'], 'khop auth0_id lan 2');

// 3) User hoan toan moi -> INSERT
$row3 = resolve_local_user($conn, [
    'auth0_id' => 'auth0|new', 'email' => 'new@test.vn',
    'username' => 'newuser', 'role' => 'admin',
]);
assert_true($row3['id'] > 0, 'tao user moi');
assert_same('admin', $row3['role'], 'role admin giu nguyen');

$conn->query("DELETE FROM users WHERE email IN ('link@test.vn','new@test.vn')");
echo "auth_bridge_resolve_test ... ok\n";
