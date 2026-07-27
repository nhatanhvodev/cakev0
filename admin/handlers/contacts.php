<?php
/* admin/handlers/contacts.php — Contacts tab action handlers.
 * Ported from admin/admin.php:
 *   - handle_reply_contact()  <- reply_contact POST handler (admin.php L657-686)
 *   - handle_delete_contact() <- delete_contact POST handler (admin.php L768-787)
 * The mailer dependency is loaded by admin/bootstrap.php.
 */

function handle_reply_contact(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('contacts');
    }

    $id = (int) ($_POST['contact_id'] ?? 0);
    $email = (string) ($_POST['contact_email'] ?? '');
    $name = (string) ($_POST['contact_name'] ?? '');
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if ($id > 0 && $replyMessage !== '') {
        $stmt = $conn->prepare("UPDATE contact_requests SET status = 'replied', reply_message = ?, replied_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $replyMessage, $id);
        $stmt->execute();
        $stmt->close();

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeReply = nl2br(htmlspecialchars($replyMessage, ENT_QUOTES, 'UTF-8'));
        $subject = "Phản hồi từ Gấu Bakery";
        $body = "<p>Chào <strong>{$safeName}</strong>,</p>
                 <p>Cảm ơn bạn đã liên hệ với Gấu Bakery. Đây là phản hồi cho thắc mắc của bạn:</p>
                 <div style='background:#f9f9f9; padding:15px; border-left:4px solid #4a1d1f; margin:15px 0;'>
                    {$safeReply}
                 </div>
                 <p>Trân trọng,<br><strong>Gấu Bakery Team</strong></p>";

        if (send_custom_mail($email, $subject, $body)) {
            setAdminToast("Đã gửi phản hồi thành công!");
        } else {
            setAdminToast("Đã lưu phản hồi nhưng không thể gửi email (Lỗi SMTP).", "error");
        }
    }

    redirectToTab('contacts');
}

function handle_delete_contact(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('contacts');
    }

    $id = (int) ($_POST['contact_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_requests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted > 0) {
            setAdminToast("Đã xóa liên hệ thành công!");
        } else {
            setAdminToast("Liên hệ không tồn tại hoặc đã được xóa trước đó.", "warning");
        }
    } else {
        setAdminToast("Liên hệ không hợp lệ.", "error");
    }

    redirectToTab('contacts');
}
