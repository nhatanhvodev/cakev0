<?php
/* admin/handlers/reviews.php — Testimonials tab action handler.
 * Ported from admin/admin.php:
 *   - handle_update_review_status() <- update_review_status POST handler
 *     (admin.php L432-453)
 * Note: legacy also called regenerateCsrfToken() after the mutation; like the
 * other modular handlers, bootstrap.php keeps a stable per-session token, so
 * this preserves the CSRF-check + toast + redirect contract only.
 */

function handle_update_review_status(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('testimonials');
    }

    $reviewId = isset($_POST['review_id']) ? (int) $_POST['review_id'] : 0;
    $status = $_POST['review_status'] ?? '';
    $allowed = ['approved', 'rejected', 'pending'];

    if ($reviewId > 0 && in_array($status, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $reviewId);
        $stmt->execute();
        $stmt->close();
        setAdminToast("Đã cập nhật trạng thái đánh giá");
    } else {
        setAdminToast("Dữ liệu đánh giá không hợp lệ", "error");
    }

    redirectToTab('testimonials');
}
