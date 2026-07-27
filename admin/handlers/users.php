<?php
/* admin/handlers/users.php — Users tab action handlers.
 * Ported from admin/admin.php:
 *   - handle_delete_user() <- delete_user_id GET handler (admin.php L813-873)
 * Legacy had NO CSRF check on this GET-delete link at all. Hardened per plan
 * Global Constraints: verify a &csrf= query param with hash_equals() before
 * any mutation runs (same pattern as handlers/orders.php handle_delete_order()).
 */

function handle_delete_user(mysqli $conn): void {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '')) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('users');
    }

    $userId = (int) ($_GET['delete_user_id'] ?? 0);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("DELETE FROM login_logs WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM login_tokens WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM password_reset_requests WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM product_reviews WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE oi FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        setAdminToast("Đã xóa khách hàng thành công!");
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        setAdminToast("Xóa khách hàng thất bại. Vui lòng thử lại.", "error");
    }

    redirectToTab('users');
}
