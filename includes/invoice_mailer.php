<?php

declare(strict_types=1);

require_once __DIR__ . '/invoice_pdf.php';
require_once __DIR__ . '/mailer.php';

function invoice_email_delivery_lock_name(int $orderId): string
{
    return 'gau_bakery_invoice_email_order_' . $orderId;
}

function should_send_invoice_email(array $order): bool
{
    return empty($order['invoice_email_sent_at']);
}

function acquire_invoice_email_delivery_lock(mysqli $conn, int $orderId, int $timeoutSeconds = 0): ?bool
{
    $lockName = invoice_email_delivery_lock_name($orderId);
    $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS lock_acquired');
    if (!$stmt) {
        error_log('Invoice Mail Error: Failed to prepare advisory lock acquisition for order #' . $orderId);
        return null;
    }

    $stmt->bind_param('si', $lockName, $timeoutSeconds);
    if (!$stmt->execute()) {
        $stmt->close();
        error_log('Invoice Mail Error: Failed to execute advisory lock acquisition for order #' . $orderId);
        return null;
    }

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return match ((int) ($result['lock_acquired'] ?? 0)) {
        1 => true,
        0 => false,
        default => null,
    };
}

function release_invoice_email_delivery_lock(mysqli $conn, int $orderId): void
{
    $lockName = invoice_email_delivery_lock_name($orderId);
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?) AS lock_released');
    if (!$stmt) {
        error_log('Invoice Mail Error: Failed to prepare advisory lock release for order #' . $orderId);
        return;
    }

    $stmt->bind_param('s', $lockName);
    if (!$stmt->execute()) {
        $stmt->close();
        error_log('Invoice Mail Error: Failed to execute advisory lock release for order #' . $orderId);
        return;
    }

    $stmt->close();
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

function mark_order_invoice_email_sent(mysqli $conn, int $orderId): bool
{
    $updateStmt = $conn->prepare(
        "UPDATE orders
         SET invoice_email_sent_at = NOW()
         WHERE id = ? AND invoice_email_sent_at IS NULL"
    );

    if (!$updateStmt) {
        error_log('Invoice Mail Error: Failed to prepare sent marker update for order #' . $orderId);
        return false;
    }

    $updateStmt->bind_param('i', $orderId);
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        error_log('Invoice Mail Error: Failed to execute sent marker update for order #' . $orderId);
        return false;
    }

    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affectedRows !== 1) {
        error_log('Invoice Mail Error: Unexpected sent marker write result for order #' . $orderId);
        return false;
    }

    return true;
}

function send_order_invoice_email(mysqli $conn, int $orderId): bool
{
    $lockAcquired = acquire_invoice_email_delivery_lock($conn, $orderId);
    if ($lockAcquired === false) {
        return true;
    }

    if ($lockAcquired === null) {
        error_log('Invoice Mail Error: Advisory lock unavailable for order #' . $orderId);
        return false;
    }

    try {
        $payload = load_invoice_order_payload($conn, $orderId);
        if ($payload === null) {
            return false;
        }

        $order = $payload['order'];
        if (!should_send_invoice_email($order)) {
            return true;
        }

        $email = trim((string) ($order['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Invoice Mail Error: Missing or invalid recipient email for order #' . $orderId);
            return false;
        }

        $items = $payload['items'];
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
            return false;
        }

        return mark_order_invoice_email_sent($conn, $orderId);
    } finally {
        release_invoice_email_delivery_lock($conn, $orderId);
    }
}
