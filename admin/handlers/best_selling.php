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

    $productIds = array_values(array_unique(array_filter(array_map(
        'intval',
        (array) ($_POST['product_ids'] ?? [])
    ), static fn($id) => $id > 0)));
    $manualBest = $_POST['manual_best'] ?? [];
    $bestRank = $_POST['best_rank'] ?? [];

    if (!$productIds) {
        setAdminToast("Không có sản phẩm nào để cập nhật.", "warning");
        redirectToTab('best-selling');
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $visibleStmt = $conn->prepare("SELECT id FROM banh WHERE is_hidden = 0 AND id IN ({$placeholders})");
    $types = str_repeat('i', count($productIds));
    $visibleStmt->bind_param($types, ...$productIds);
    $visibleStmt->execute();
    $visibleRows = $visibleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $visibleStmt->close();

    $visibleIds = [];
    foreach ($visibleRows as $row) {
        $visibleIds[(int) $row['id']] = true;
    }

    $stmt = $conn->prepare("UPDATE banh SET is_best_manual = ?, best_rank = ? WHERE id = ? AND is_hidden = 0");
    $updated = 0;

    $conn->begin_transaction();
    try {
        foreach ($productIds as $id) {
            if (!isset($visibleIds[$id])) {
                continue;
            }
            $isBest = isset($manualBest[$id]) ? 1 : 0;
            $rank = $isBest ? max(0, (int) ($bestRank[$id] ?? 0)) : 0;
            $stmt->bind_param('iii', $isBest, $rank, $id);
            $stmt->execute();
            $updated++;
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $stmt->close();
        setAdminToast("Cập nhật Best Selling thất bại: " . $e->getMessage(), "error");
        redirectToTab('best-selling');
    }

    $stmt->close();
    setAdminToast("Đã cập nhật best selling cho {$updated} sản phẩm");
    redirectToTab('best-selling');
}
