<?php
/* admin/handlers/orders.php — Orders tab action handlers.
 * Ported from admin/admin.php:
 *   - handle_update_order_statuses() <- update_order_statuses POST handler (admin.php L458-512)
 *   - handle_delete_order()          <- delete_order_id GET handler (admin.php L875-902)
 * Both call the shared setAdminToast()/redirectToTab() from admin/bootstrap.php.
 * Note: legacy also called regenerateCsrfToken() after each mutation; the modular
 * admin's bootstrap.php does not define/use that rotation scheme, so it is
 * intentionally omitted here (kept the CSRF-check + toast + redirect contract only).
 */
require_once __DIR__ . '/../../includes/order_helpers.php';

function handle_update_order_statuses(mysqli $conn): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('orders');
    }

    $commonAllowedStatuses = ['pending', 'paid', 'approved', 'delivering', 'delivered', 'completed', 'cancelled', 'failed'];
    $codOnlyStatuses = ['cod_not_deposited', 'cod_deposited'];
    $allowedStatuses = array_merge($commonAllowedStatuses, $codOnlyStatuses);
    $selected = $_POST['selected_orders'] ?? [];
    $order_statuses = $_POST['order_status'] ?? [];

    if (empty($selected)) {
        setAdminToast("Vui lòng chọn ít nhất một đơn hàng", "warning");
        redirectToTab('orders');
    }

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $paymentStmt = $conn->prepare("SELECT payment_method, status FROM orders WHERE id = ? LIMIT 1");
    $updated = 0;

    foreach ($selected as $id) {
        $id = (int) $id;
        $status = strtolower(trim((string) ($order_statuses[$id] ?? '')));
        if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
            continue;
        }

        $paymentStmt->bind_param("i", $id);
        $paymentStmt->execute();
        $orderMeta = $paymentStmt->get_result()->fetch_assoc();
        if (!$orderMeta) {
            continue;
        }

        $isCodOrder = isCodPaymentMethod((string) ($orderMeta['payment_method'] ?? ''));
        $shouldSendInvoice = $isCodOrder && $status === 'approved';
        if (in_array($status, $codOnlyStatuses, true) && !$isCodOrder) {
            continue;
        }

        $stmt->bind_param("si", $status, $id);
        if (!$stmt->execute()) {
            continue;
        }

        if ($shouldSendInvoice) {
            send_order_invoice_email($conn, $id);
        }

        $updated++;
    }

    $paymentStmt->close();
    $stmt->close();
    setAdminToast("Đã cập nhật trạng thái cho $updated đơn hàng");
    redirectToTab('orders');
}

function handle_delete_order(mysqli $conn): void {
    // GET-triggered delete — legacy had no CSRF check on this link at all
    // (admin.php L875). Hardened per plan Global Constraints: verify the
    // csrf query param before touching the database.
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'] ?? '')) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('orders');
    }

    $orderId = (int) ($_GET['delete_order_id'] ?? 0);

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("DELETE FROM product_reviews WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        setAdminToast("Đã xóa đơn hàng thành công!");
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        setAdminToast("Xóa đơn hàng thất bại: " . $e->getMessage(), "error");
    }

    redirectToTab('orders');
}
