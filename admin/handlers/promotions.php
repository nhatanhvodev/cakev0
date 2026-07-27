<?php
/* admin/handlers/promotions.php — Promotions tab action handlers.
 * Ported from admin/admin.php:
 *   - handle_add_promotion()    <- add_promotion POST handler (admin.php L515-521)
 *   - handle_update_promotion() <- update_promotion POST handler (admin.php L524-530)
 *   - handle_delete_promotion() <- delete_promotion_id GET handler (admin.php L533-537)
 * GET delete is hardened with &csrf= per the migration plan.
 */

function handle_add_promotion(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('promotions');
    }

    $banhId = isset($_POST['banh_id']) ? (int) $_POST['banh_id'] : 0;
    $giaKhuyenMai = isset($_POST['gia_khuyen_mai']) ? (int) $_POST['gia_khuyen_mai'] : 0;
    $ngayBatDau = (string) ($_POST['ngay_bat_dau'] ?? '');
    $ngayKetThuc = (string) ($_POST['ngay_ket_thuc'] ?? '');

    if ($banhId <= 0 || $giaKhuyenMai <= 0 || $ngayBatDau === '' || $ngayKetThuc === '') {
        setAdminToast("Dữ liệu khuyến mãi không hợp lệ", "error");
        redirectToTab('promotions');
    }

    $stmt = $conn->prepare("INSERT INTO promotions (banh_id, gia_khuyen_mai, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $banhId, $giaKhuyenMai, $ngayBatDau, $ngayKetThuc);
    $stmt->execute();
    $stmt->close();
    setAdminToast("Đã thêm khuyến mãi thành công!");
    redirectToTab('promotions');
}

function handle_update_promotion(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('promotions');
    }

    $promotionId = isset($_POST['promotion_id']) ? (int) $_POST['promotion_id'] : 0;
    $giaKhuyenMai = isset($_POST['gia_khuyen_mai']) ? (int) $_POST['gia_khuyen_mai'] : 0;
    $ngayBatDau = (string) ($_POST['ngay_bat_dau'] ?? '');
    $ngayKetThuc = (string) ($_POST['ngay_ket_thuc'] ?? '');

    if ($promotionId <= 0 || $giaKhuyenMai <= 0 || $ngayBatDau === '' || $ngayKetThuc === '') {
        setAdminToast("Dữ liệu khuyến mãi không hợp lệ", "error");
        redirectToTab('promotions');
    }

    $stmt = $conn->prepare("UPDATE promotions SET gia_khuyen_mai = ?, ngay_bat_dau = ?, ngay_ket_thuc = ? WHERE id = ?");
    $stmt->bind_param("issi", $giaKhuyenMai, $ngayBatDau, $ngayKetThuc, $promotionId);
    $stmt->execute();
    $stmt->close();
    setAdminToast("Đã cập nhật khuyến mãi thành công!");
    redirectToTab('promotions');
}

function handle_delete_promotion(mysqli $conn): void
{
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '')) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('promotions');
    }

    $promotionId = (int) ($_GET['delete_promotion_id'] ?? 0);
    if ($promotionId > 0) {
        $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->bind_param("i", $promotionId);
        $stmt->execute();
        $stmt->close();
        setAdminToast("Đã xóa khuyến mãi thành công!");
    }

    redirectToTab('promotions');
}
