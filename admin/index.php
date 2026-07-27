<?php
require_once __DIR__ . '/bootstrap.php';

// POST dispatch stub — future handlers plug in here, each CSRF-checked.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast('Phiên làm việc hết hạn, vui lòng thử lại.', 'error');
        redirectToTab($_POST['tab'] ?? 'dashboard');
    }
    // (handlers/*.php dispatched here as tabs are ported)
}

$allowed = ['dashboard','orders','products','best-selling','testimonials',
  'password-requests','users','promotions','coupons','contacts','chat'];
$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, $allowed, true)) { $tab = 'dashboard'; }

require __DIR__ . '/views/layout.php';
