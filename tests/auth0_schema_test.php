<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../config/connect.php';
require __DIR__ . '/../includes/auth0_schema.php';

ensureAuth0Infrastructure($conn);

$res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
assert_true($res && $res->num_rows === 1, 'cot auth0_id ton tai');

// Idempotent: goi lai khong loi
ensureAuth0Infrastructure($conn);
$res2 = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
assert_true($res2 && $res2->num_rows === 1, 'goi lai van 1 cot');

echo "auth0_schema_test ... ok\n";
