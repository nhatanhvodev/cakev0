<?php
// admin/bootstrap.php — request context for the modular admin.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/coupons.php';
require_once __DIR__ . '/../config/uploadthing.php';
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/invoice_mailer.php';

if (function_exists('ensureCartCouponInfrastructure')) {
    ensureCartCouponInfrastructure($conn);
}

// Auth guard — identical contract to legacy admin.php.
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../pages/login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('setAdminToast')) {
    function setAdminToast($msg, $type = 'success') {
        $_SESSION['admin_toast'] = ['msg' => $msg, 'type' => $type];
    }
}
if (!function_exists('redirectToTab')) {
    function redirectToTab(string $tab): void {
        header("Location: index.php?tab={$tab}#{$tab}");
        exit;
    }
}
