<?php
require __DIR__ . '/bootstrap.php';

$map = [
    'pages/login.php' => 'pages/auth/login.php',
    'pages/register.php' => 'mode=signup',
    'pages/logout.php' => 'pages/auth/logout.php',
    'pages/verify-registration.php' => 'pages/auth/login.php',
    'pages/forgot-password.php' => 'pages/auth/login.php',
];

foreach ($map as $file => $needle) {
    $src = file_get_contents(__DIR__ . '/../' . $file);
    assert_true(str_contains($src, $needle), "$file redirect toi $needle");
    assert_true(!str_contains($src, 'password_verify'), "$file khong con verify mat khau");
}

$adminTopbar = file_get_contents(__DIR__ . '/../admin/views/partials/topbar.php');
assert_true(str_contains($adminTopbar, '../pages/auth/logout.php'), 'admin topbar dung Auth0 logout');
assert_true(!str_contains($adminTopbar, 'admin.php?logout=1'), 'admin topbar khong dung logout legacy');

$legacyAdmin = file_get_contents(__DIR__ . '/../admin/admin.php');
assert_true(str_contains($legacyAdmin, 'Location: ../pages/auth/logout.php'), 'admin.php logout chuyen qua Auth0 logout');

echo "auth_legacy_redirect_test ... ok\n";
