<?php
require_once __DIR__ . '/bootstrap.php';

$allowed = ['dashboard','orders','products','best-selling','testimonials',
  'password-requests','users','promotions','coupons','contacts','chat'];

// ---------------------------------------------------------------------------
// GET-delete dispatch scaffold.
// Legacy admin.php exposes `?delete_<entity>_id=N` links with NO CSRF check
// (a known vulnerability — see plan's Global Constraints). The modular admin
// hardens this: every GET delete must carry `&csrf=<token>` verified with
// hash_equals() before any mutation runs. Handlers live in
// admin/handlers/<domain>.php and, like POST actions, call setAdminToast()
// then redirectToTab() when done.
//
// Wire a route here per tab as it is ported, e.g.:
//   if (isset($_GET['delete_order_id']) && admin_csrf_get_ok()) {
//       require_once __DIR__ . '/handlers/orders.php';
//       handle_delete_order_id($conn, (int) $_GET['delete_order_id']);
//   }
// ---------------------------------------------------------------------------
if (!function_exists('admin_csrf_get_ok')) {
    function admin_csrf_get_ok(): bool {
        return hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '');
    }
}

// (GET-delete routes plug in here, one per tab, each guarded by admin_csrf_get_ok())
if (isset($_GET['delete_order_id'])) {
    require_once __DIR__ . '/handlers/orders.php';
    handle_delete_order($conn);
}
if (isset($_GET['delete_product_id'])) {
    require_once __DIR__ . '/handlers/products.php';
    handle_delete_product($conn);
}
if (isset($_GET['delete_user_id'])) {
    require_once __DIR__ . '/handlers/users.php';
    handle_delete_user($conn);
}
if (isset($_GET['delete_promotion_id'])) {
    require_once __DIR__ . '/handlers/promotions.php';
    handle_delete_promotion($conn);
}
if (isset($_GET['delete_coupon_id'])) {
    require_once __DIR__ . '/handlers/coupons.php';
    handle_delete_coupon($conn);
}

// POST dispatch — CSRF-checked, then routed to handlers/<domain>.php per action.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast('Phiên làm việc hết hạn, vui lòng thử lại.', 'error');
        redirectToTab(in_array($_POST['tab'] ?? '', $allowed, true) ? $_POST['tab'] : 'dashboard');
    }

    // -----------------------------------------------------------------
    // Action router — one require + handler call per POST action, added
    // as each tab is ported (see docs/superpowers/plans/
    // 2026-07-27-admin-migrate-remaining-tabs.md, "Porting Contract").
    // Example:
    //   if (isset($_POST['update_order_statuses'])) {
    //       require_once __DIR__ . '/handlers/orders.php';
    //       handle_update_order_statuses($conn);
    //   }
    // -----------------------------------------------------------------
    if (isset($_POST['update_order_statuses'])) {
        require_once __DIR__ . '/handlers/orders.php';
        handle_update_order_statuses($conn);
    }
    if (isset($_POST['add_product'])) {
        require_once __DIR__ . '/handlers/products.php';
        handle_add_product($conn);
    }
    if (isset($_POST['update_product'])) {
        require_once __DIR__ . '/handlers/products.php';
        handle_update_product($conn);
    }
    if (isset($_POST['delete_product_image'])) {
        require_once __DIR__ . '/handlers/products.php';
        handle_delete_product_image($conn);
    }
    if (isset($_POST['update_best_selling'])) {
        require_once __DIR__ . '/handlers/best_selling.php';
        handle_update_best_selling($conn);
    }
    if (isset($_POST['update_review_status'])) {
        require_once __DIR__ . '/handlers/reviews.php';
        handle_update_review_status($conn);
    }
    if (isset($_POST['update_password_request_status'])) {
        require_once __DIR__ . '/handlers/password_requests.php';
        handle_update_password_request_status($conn);
    }
    if (isset($_POST['add_promotion'])) {
        require_once __DIR__ . '/handlers/promotions.php';
        handle_add_promotion($conn);
    }
    if (isset($_POST['update_promotion'])) {
        require_once __DIR__ . '/handlers/promotions.php';
        handle_update_promotion($conn);
    }
    if (isset($_POST['add_coupon'])) {
        require_once __DIR__ . '/handlers/coupons.php';
        handle_add_coupon($conn);
    }
    if (isset($_POST['update_coupon'])) {
        require_once __DIR__ . '/handlers/coupons.php';
        handle_update_coupon($conn);
    }
    if (isset($_POST['reply_contact'])) {
        require_once __DIR__ . '/handlers/contacts.php';
        handle_reply_contact($conn);
    }
    if (isset($_POST['delete_contact'])) {
        require_once __DIR__ . '/handlers/contacts.php';
        handle_delete_contact($conn);
    }
}

$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, $allowed, true)) { $tab = 'dashboard'; }

require __DIR__ . '/views/layout.php';
