<?php
/* admin/handlers/coupons.php — Coupons tab action handlers.
 * Ported from admin/admin.php:
 *   - handle_add_coupon()    <- add_coupon POST handler (admin.php L541-589)
 *   - handle_update_coupon() <- update_coupon POST handler (admin.php L592-643)
 *   - handle_delete_coupon() <- delete_coupon_id GET handler (admin.php L646-653)
 * Coupon helper functions are loaded by admin/bootstrap.php via config/coupons.php.
 * GET delete is hardened with &csrf= per the migration plan.
 */

function handle_add_coupon(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('coupons');
    }

    $code = normalizeCouponCode((string) ($_POST['code'] ?? ''));
    $discountPercent = (float) ($_POST['discount_percent'] ?? 0);
    $minSubtotal = max(0, (float) ($_POST['min_subtotal'] ?? 0));
    $usageLimit = parseCouponUsageLimit($_POST['usage_limit'] ?? null);
    $startsAt = formatCouponDateValue($_POST['starts_at'] ?? null);
    $endsAt = formatCouponDateValue($_POST['ends_at'] ?? null);
    $startsAt = $startsAt !== '' ? $startsAt : null;
    $endsAt = $endsAt !== '' ? $endsAt : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
        setAdminToast("Mã coupon không hợp lệ", "error");
        redirectToTab('coupons');
    }

    if ($discountPercent <= 0 || $discountPercent > 100) {
        setAdminToast("Phần trăm giảm giá cần nằm trong khoảng 1-100", "error");
        redirectToTab('coupons');
    }

    if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
        setAdminToast("Thời gian áp dụng coupon không hợp lệ", "error");
        redirectToTab('coupons');
    }

    $stmt = $conn->prepare(
        "INSERT INTO cart_coupons (code, discount_percent, min_subtotal, usage_limit, is_active, starts_at, ends_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "sddiiss",
        $code,
        $discountPercent,
        $minSubtotal,
        $usageLimit,
        $isActive,
        $startsAt,
        $endsAt
    );

    if ($stmt->execute()) {
        setAdminToast("Đã thêm coupon thành công!");
    } else {
        setAdminToast("Không thể thêm coupon. Có thể mã đã tồn tại.", "error");
    }
    $stmt->close();
    redirectToTab('coupons');
}

function handle_update_coupon(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('coupons');
    }

    $couponId = (int) ($_POST['coupon_id'] ?? 0);
    $code = normalizeCouponCode((string) ($_POST['code'] ?? ''));
    $discountPercent = (float) ($_POST['discount_percent'] ?? 0);
    $minSubtotal = max(0, (float) ($_POST['min_subtotal'] ?? 0));
    $usageLimit = parseCouponUsageLimit($_POST['usage_limit'] ?? null);
    $startsAt = formatCouponDateValue($_POST['starts_at'] ?? null);
    $endsAt = formatCouponDateValue($_POST['ends_at'] ?? null);
    $startsAt = $startsAt !== '' ? $startsAt : null;
    $endsAt = $endsAt !== '' ? $endsAt : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($couponId <= 0 || !preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
        setAdminToast("Dữ liệu coupon không hợp lệ", "error");
        redirectToTab('coupons');
    }

    if ($discountPercent <= 0 || $discountPercent > 100) {
        setAdminToast("Phần trăm giảm giá cần nằm trong khoảng 1-100", "error");
        redirectToTab('coupons');
    }

    if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
        setAdminToast("Thời gian áp dụng coupon không hợp lệ", "error");
        redirectToTab('coupons');
    }

    $stmt = $conn->prepare(
        "UPDATE cart_coupons
         SET code = ?, discount_percent = ?, min_subtotal = ?, usage_limit = ?, is_active = ?, starts_at = ?, ends_at = ?
         WHERE id = ?"
    );
    $stmt->bind_param(
        "sddiissi",
        $code,
        $discountPercent,
        $minSubtotal,
        $usageLimit,
        $isActive,
        $startsAt,
        $endsAt,
        $couponId
    );

    if ($stmt->execute()) {
        setAdminToast("Đã cập nhật coupon thành công!");
    } else {
        setAdminToast("Không thể cập nhật coupon. Có thể mã đã tồn tại.", "error");
    }
    $stmt->close();
    redirectToTab('coupons');
}

function handle_delete_coupon(mysqli $conn): void
{
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '')) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('coupons');
    }

    $couponId = (int) ($_GET['delete_coupon_id'] ?? 0);
    if ($couponId > 0) {
        $stmt = $conn->prepare("DELETE FROM cart_coupons WHERE id = ?");
        $stmt->bind_param("i", $couponId);
        $stmt->execute();
        $stmt->close();
        setAdminToast("Đã xóa coupon thành công!");
    }

    redirectToTab('coupons');
}
