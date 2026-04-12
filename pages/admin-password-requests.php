<?php
session_start();
require_once '../config/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = $_GET['id'];

    if ($action === 'approve') {

        $sql = "SELECT user_id, new_password FROM password_reset_requests WHERE id = ? AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();

        if ($request) {

            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $request['new_password'], $request['user_id']);
            $stmt->execute();

            $sql = "UPDATE password_reset_requests SET status = 'approved', approved_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            echo "Yêu cầu đã được duyệt.";
        }
    } elseif ($action === 'reject') {
        $sql = "UPDATE password_reset_requests SET status = 'rejected' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        echo "Yêu cầu đã bị từ chối.";
    }
}

$sql = "SELECT r.id, r.user_id, r.status, r.created_at, u.username 
        FROM password_reset_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Quản lý yêu cầu đặt lại mật khẩu</title>
</head>
<body>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #ffffff; color: #272727; margin: 0; padding: 24px; }
        h2 { margin: 0 0 16px; color: #4a1d1f; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid #f3e0be; border-radius: 14px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; min-width: 640px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #f3e0be; text-align: left; }
        th { background: #fdf1db; color: #4a1d1f; font-weight: 600; }
        a { color: #4a1d1f; text-decoration: none; }
        a:hover { text-decoration: underline; }
        @media (max-width: 600px) { body { padding: 16px; } }
    </style>
    <h2>Danh sách yêu cầu đặt lại mật khẩu</h2>
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
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['username']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td>
                    <?php if ($row['status'] === 'pending'): ?>
                        <a href="?action=approve&id=<?php echo $row['id']; ?>">Duyệt</a> |
                        <a href="?action=reject&id=<?php echo $row['id']; ?>">Từ chối</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>