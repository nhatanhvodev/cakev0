<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config/connect.php';

if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_id'])) {
    header("Location: /cakev0/admin/admin.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flashMsg = '';
$flashType = 'success';

if (isset($_SESSION['admin_password_request_flash'])) {
    $flash = $_SESSION['admin_password_request_flash'];
    $flashMsg = $flash['msg'] ?? '';
    $flashType = $flash['type'] ?? 'success';
    unset($_SESSION['admin_password_request_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password_request_status'])) {
    $csrf = $_POST['csrf_token'] ?? '';

    if (empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['admin_password_request_flash'] = [
            'msg' => 'Yêu cầu không hợp lệ (CSRF).',
            'type' => 'danger'
        ];
        header('Location: /cakev0/pages/admin-password-requests.php');
        exit;
    }

    $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    $request_status = trim((string) ($_POST['request_status'] ?? ''));

    if ($request_id <= 0 || !in_array($request_status, ['approved', 'rejected'], true)) {
        $_SESSION['admin_password_request_flash'] = [
            'msg' => 'Thao tác không hợp lệ.',
            'type' => 'danger'
        ];
        header('Location: /cakev0/pages/admin-password-requests.php');
        exit;
    }

    if ($request_status === 'approved') {
        // Fetch user email along with the request details
        $sql = "SELECT r.user_id, r.new_password, u.email, u.username 
                FROM password_reset_requests r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ? AND r.status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($request) {
            $user_id = $request['user_id'];
            $new_password_hash = $request['new_password'];
            $user_email = $request['email'];
            $username = $request['username'];

            // 1. Update user password
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_password_hash, $user_id);
            $stmt->execute();
            $stmt->close();

            // 2. Update request status
            $sql = "UPDATE password_reset_requests SET status = 'approved', approved_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            // 3. Send notification email
            require_once '../includes/mailer.php';
            $subject = "Thông báo: Mật khẩu Gấu Bakery của bạn đã được cập nhật";
            $body = "<h3>Chào mừng quay trở lại, {$username}!</h3>
                     <p>Yêu cầu đặt lại mật khẩu của bạn đã được quản trị viên phê duyệt thành công.</p>
                     <p>Giờ đây bạn có thể đăng nhập vào hệ thống bằng mật khẩu mới mà bạn đã đăng ký.</p>
                     <p>Nếu bạn không thực hiện yêu cầu này, vui lòng liên hệ ngay với chúng tôi để được hỗ trợ.</p>
                     <br>
                     <p>Trân trọng,<br><strong>Gấu Bakery Team</strong></p>";
            
            send_custom_mail($user_email, $subject, $body);

            $_SESSION['admin_password_request_flash'] = [
                'msg' => 'Đã duyệt và gửi email thông báo cho khách hàng.',
                'type' => 'success'
            ];
        } else {
            $_SESSION['admin_password_request_flash'] = [
                'msg' => 'Yêu cầu không tồn tại hoặc đã được xử lý trước đó.',
                'type' => 'warning'
            ];
        }
    }

    if ($request_status === 'rejected') {
        $sql = "UPDATE password_reset_requests SET status = 'rejected', approved_at = NULL WHERE id = ? AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            $_SESSION['admin_password_request_flash'] = [
                'msg' => 'Đã từ chối yêu cầu đổi mật khẩu.',
                'type' => 'success'
            ];
        } else {
            $_SESSION['admin_password_request_flash'] = [
                'msg' => 'Yêu cầu không tồn tại hoặc đã được xử lý trước đó.',
                'type' => 'warning'
            ];
        }
    }

    header('Location: /cakev0/pages/admin-password-requests.php');
    exit;
}

$sql = "SELECT r.id, r.user_id, r.status, r.created_at, u.username 
        FROM password_reset_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);

$statusLabels = [
    'pending' => ['label' => 'Chờ duyệt', 'class' => 'warning text-dark'],
    'approved' => ['label' => 'Đã duyệt', 'class' => 'success'],
    'rejected' => ['label' => 'Đã từ chối', 'class' => 'danger']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản lý yêu cầu đặt lại mật khẩu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #ffffff; color: #272727; margin: 0; padding: 24px; }
        h2 { margin: 0; color: #4a1d1f; }
        .head-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .back-link { border: 1px solid #4a1d1f; border-radius: 999px; padding: 8px 14px; text-decoration: none; font-weight: 600; color: #4a1d1f; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid #f3e0be; border-radius: 14px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #f3e0be; text-align: left; }
        th { background: #fdf1db; color: #4a1d1f; font-weight: 600; }
        td.actions { white-space: nowrap; }
        .alert { margin-bottom: 12px; }
        @media (max-width: 600px) {
            body { padding: 16px; }
            .head-row { flex-direction: column; align-items: flex-start; }
        }
    </style>

    <div class="head-row">
        <h2>Danh sách yêu cầu đặt lại mật khẩu</h2>
        <a class="back-link" href="/cakev0/admin/admin.php?tab=dashboard#dashboard">Quay lại Admin</a>
    </div>

    <?php if ($flashMsg !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?>" role="alert">
            <?= htmlspecialchars($flashMsg) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th>
                <th>Tên người dùng</th>
                <th>Trạng thái</th>
                <th>Thời gian yêu cầu</th>
                <th>Hành động</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= (int) $row['id']; ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td>
                    <?php
                    $status = (string) ($row['status'] ?? 'pending');
                    $statusData = $statusLabels[$status] ?? ['label' => ucfirst($status), 'class' => 'secondary'];
                    ?>
                    <span class="badge bg-<?= htmlspecialchars($statusData['class']) ?>">
                        <?= htmlspecialchars($statusData['label']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($row['created_at']); ?></td>
                <td class="actions">
                    <?php if (($row['status'] ?? '') === 'pending'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="request_status" value="approved">
                            <button type="submit" name="update_password_request_status" class="btn btn-sm btn-success">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="request_status" value="rejected">
                            <button type="submit" name="update_password_request_status" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted">Đã xử lý</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>