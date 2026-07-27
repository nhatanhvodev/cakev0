<?php
/* admin/handlers/password_requests.php — Password-requests tab action handler.
 * Ported from admin/admin.php:
 *   - handle_update_password_request_status() <- update_password_request_status
 *     POST handler (admin.php L689-765)
 * The mailer dependency is loaded by admin/bootstrap.php.
 */

function handle_update_password_request_status(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phiên làm việc hết hạn, vui lòng thử lại.", "error");
        redirectToTab('password-requests');
    }

    $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    $requestStatus = trim((string) ($_POST['request_status'] ?? ''));

    if ($requestId <= 0 || !in_array($requestStatus, ['approved', 'rejected'], true)) {
        setAdminToast("Thao tac khong hop le.", "error");
        redirectToTab('password-requests');
    }

    if ($requestStatus === 'approved') {
        $stmt = $conn->prepare(
            "SELECT r.user_id, r.new_password, u.email, u.username
             FROM password_reset_requests r
             JOIN users u ON r.user_id = u.id
             WHERE r.id = ? AND r.status = 'pending'"
        );
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($request) {
            $userId = (int) $request['user_id'];
            $newPasswordHash = (string) $request['new_password'];
            $userEmail = (string) $request['email'];
            $username = (string) $request['username'];

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newPasswordHash, $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "UPDATE password_reset_requests
                 SET status = 'approved', approved_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();

            $subject = "Thong bao: Mat khau Gau Bakery cua ban da duoc cap nhat";
            $body = "<h3>Chao mung quay tro lai, {$username}!</h3>
                     <p>Yeu cau dat lai mat khau cua ban da duoc quan tri vien phe duyet thanh cong.</p>
                     <p>Bay gio ban co the dang nhap vao he thong bang mat khau moi ma ban da dang ky.</p>
                     <p>Neu ban khong thuc hien yeu cau nay, vui long lien he ngay voi chung toi de duoc ho tro.</p>
                     <br>
                     <p>Tran trong,<br><strong>Gau Bakery Team</strong></p>";

            send_custom_mail($userEmail, $subject, $body);
            setAdminToast("Da duyet yeu cau doi mat khau thanh cong.");
        } else {
            setAdminToast("Yeu cau khong ton tai hoac da duoc xu ly truoc do.", "warning");
        }
    }

    if ($requestStatus === 'rejected') {
        $stmt = $conn->prepare(
            "UPDATE password_reset_requests
             SET status = 'rejected', approved_at = NULL
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            setAdminToast("Da tu choi yeu cau doi mat khau.");
        } else {
            setAdminToast("Yeu cau khong ton tai hoac da duoc xu ly truoc do.", "warning");
        }
    }

    redirectToTab('password-requests');
}
