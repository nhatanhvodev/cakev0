<?php

declare(strict_types=1);

require_once __DIR__ . '/invoice_pdf.php';
require_once __DIR__ . '/mailer.php';

function should_send_invoice_email(array $order): bool
{
    return empty($order['invoice_email_sent_at']);
}

function load_invoice_order_payload(mysqli $conn, int $orderId): ?array
{
    $stmt = $conn->prepare(
        "SELECT o.*, u.email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('Invoice Mail Error: Failed to prepare order lookup for order #' . $orderId);
        return null;
    }

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if ($order === null) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT b.ten_banh, oi.quantity, oi.price
         FROM order_items oi
         JOIN banh b ON b.id = oi.banh_id
         WHERE oi.order_id = ?"
    );

    if (!$stmt) {
        error_log('Invoice Mail Error: Failed to prepare item lookup for order #' . $orderId);
        return null;
    }

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'order' => $order,
        'items' => $items,
    ];
}

function send_order_invoice_email(mysqli $conn, int $orderId): bool
{
    if (!$conn->begin_transaction()) {
        error_log('Invoice Mail Error: Failed to begin transaction for order #' . $orderId);
        return false;
    }

    $orderStmt = $conn->prepare(
        "SELECT o.*, u.email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1
         FOR UPDATE"
    );

    if (!$orderStmt) {
        error_log('Invoice Mail Error: Failed to prepare locked order lookup for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $orderStmt->bind_param('i', $orderId);
    if (!$orderStmt->execute()) {
        $orderStmt->close();
        error_log('Invoice Mail Error: Failed to execute locked order lookup for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $order = $orderStmt->get_result()->fetch_assoc() ?: null;
    $orderStmt->close();

    if ($order === null) {
        $conn->rollback();
        return false;
    }

    if (!should_send_invoice_email($order)) {
        if (!$conn->commit()) {
            error_log('Invoice Mail Error: Failed to commit duplicate-send guard for order #' . $orderId);
            $conn->rollback();
            return false;
        }

        return true;
    }

    $email = trim((string) ($order['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('Invoice Mail Error: Missing or invalid recipient email for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $itemStmt = $conn->prepare(
        "SELECT b.ten_banh, oi.quantity, oi.price
         FROM order_items oi
         JOIN banh b ON b.id = oi.banh_id
         WHERE oi.order_id = ?"
    );

    if (!$itemStmt) {
        error_log('Invoice Mail Error: Failed to prepare item lookup for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $itemStmt->bind_param('i', $orderId);
    if (!$itemStmt->execute()) {
        $itemStmt->close();
        error_log('Invoice Mail Error: Failed to execute item lookup for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    $pdf = render_invoice_pdf($order, $items);
    $filename = build_invoice_filename($orderId);
    $subject = 'Hoa don don hang #' . $orderId . ' tu Gau Bakery';
    $body = '<p>Don hang cua ban da duoc xac nhan hoac thanh toan thanh cong. Vui long xem hoa don PDF dinh kem.</p>';

    $sent = send_custom_mail_with_attachments(
        $email,
        $subject,
        $body,
        [[
            'filename' => $filename,
            'mime' => 'application/pdf',
            'content' => $pdf,
        ]]
    );

    if (!$sent) {
        error_log('Invoice Mail Error: Failed to send invoice for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $updateStmt = $conn->prepare(
        "UPDATE orders
         SET invoice_email_sent_at = NOW()
         WHERE id = ? AND invoice_email_sent_at IS NULL"
    );

    if (!$updateStmt) {
        error_log('Invoice Mail Error: Failed to prepare sent marker update for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $updateStmt->bind_param('i', $orderId);
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        error_log('Invoice Mail Error: Failed to execute sent marker update for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        error_log('Invoice Mail Error: Unexpected sent marker write result for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    $updateStmt->close();

    if (!$conn->commit()) {
        error_log('Invoice Mail Error: Failed to commit sent marker update for order #' . $orderId);
        $conn->rollback();
        return false;
    }

    return true;
}
