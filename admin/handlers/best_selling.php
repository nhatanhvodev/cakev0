<?php
/* admin/handlers/best_selling.php — Best-selling tab action handler.
 * Ported from admin/admin.php:
 *   - handle_update_best_selling() <- update_best_selling POST handler (admin.php L402-430)
 * Note: legacy also called regenerateCsrfToken() after the mutation; like
 * admin/handlers/orders.php and admin/handlers/products.php, the modular
 * admin's bootstrap.php does not define/use that rotation scheme, so it is
 * intentionally omitted here (kept the CSRF-check + toast + redirect contract only).
 */

function handle_update_best_selling(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('best-selling');
    }

    $productIds = $_POST['product_ids'] ?? [];
    $manualBest = $_POST['manual_best'] ?? [];
    $bestRank = $_POST['best_rank'] ?? [];

    $stmt = $conn->prepare("UPDATE banh SET is_best_manual = ?, best_rank = ? WHERE id = ?");
    $updated = 0;

    foreach ($productIds as $id) {
        $id = (int) $id;
        $isBest = isset($manualBest[$id]) ? 1 : 0;
        $rank = isset($bestRank[$id]) ? (int) $bestRank[$id] : 0;
        if ($id > 0) {
            $stmt->bind_param('iii', $isBest, $rank, $id);
            $stmt->execute();
            $updated++;
        }
    }

    $stmt->close();
    setAdminToast("Đã cập nhật best selling cho {$updated} sản phẩm");
    redirectToTab('best-selling');
}
