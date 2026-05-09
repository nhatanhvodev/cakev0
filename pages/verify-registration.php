<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../config/connect.php';

$message = 'Liên kết xác thực không hợp lệ.';
$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '') {
    $conn->set_charset('utf8mb4');

    $pendingStmt = $conn->prepare(
        'SELECT id, username, email, password_hash, expires_at
         FROM pending_registrations
         WHERE verification_token = ?
         LIMIT 1'
    );

    if ($pendingStmt) {
        $pendingStmt->bind_param('s', $token);
        $pendingStmt->execute();
        $pendingRow = $pendingStmt->get_result()->fetch_assoc();
        $pendingStmt->close();

        if (!$pendingRow) {
            $message = 'Liên kết xác thực không hợp lệ hoặc đã được sử dụng.';
        } elseif (strtotime((string) $pendingRow['expires_at']) < time()) {
            $message = 'Liên kết xác thực đã hết hạn. Vui lòng đăng ký lại.';
        } else {
            $username = (string) $pendingRow['username'];
            $email = (string) $pendingRow['email'];
            $passwordHash = (string) $pendingRow['password_hash'];
            $pendingId = (int) $pendingRow['id'];

            $conflictStmt = $conn->prepare(
                'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
            );

            if ($conflictStmt) {
                $conflictStmt->bind_param('ss', $username, $email);
                $conflictStmt->execute();
                $hasConflict = $conflictStmt->get_result()->num_rows > 0;
                $conflictStmt->close();

                if ($hasConflict) {
                    $message = 'Tên đăng nhập hoặc email đã được sử dụng. Vui lòng đăng ký lại.';
                } else {
                    $conn->begin_transaction();

                    try {
                        $insertStmt = $conn->prepare(
                            'INSERT INTO users (username, password, email) VALUES (?, ?, ?)'
                        );

                        if (!$insertStmt) {
                            throw new RuntimeException('Insert prepare failed.');
                        }

                        $insertStmt->bind_param('sss', $username, $passwordHash, $email);

                        if (!$insertStmt->execute()) {
                            $insertStmt->close();
                            throw new RuntimeException('Insert execute failed.');
                        }

                        $insertStmt->close();

                        $deleteStmt = $conn->prepare(
                            'DELETE FROM pending_registrations WHERE id = ?'
                        );

                        if (!$deleteStmt) {
                            throw new RuntimeException('Delete prepare failed.');
                        }

                        $deleteStmt->bind_param('i', $pendingId);

                        if (!$deleteStmt->execute()) {
                            $deleteStmt->close();
                            throw new RuntimeException('Delete execute failed.');
                        }

                        $deleteStmt->close();
                        $conn->commit();

                        $_SESSION['toast'] = [
                            'msg' => 'Xác thực email thành công. Bạn có thể đăng nhập ngay bây giờ.',
                            'type' => 'success',
                        ];

                        header('Location: ' . base_url('pages/login.php'));
                        $conn->close();
                        exit;
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $message = 'Không thể tạo tài khoản. Vui lòng thử lại.';
                    }
                }
            } else {
                $message = 'Không thể tạo tài khoản. Vui lòng thử lại.';
            }
        }
    } else {
        $message = 'Không thể tạo tài khoản. Vui lòng thử lại.';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực tài khoản</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f4ee;
            color: #2f241f;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .panel {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border: 1px solid #e6d5c3;
            border-radius: 12px;
            padding: 32px 28px;
            box-shadow: 0 16px 40px rgba(60, 24, 25, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        p {
            margin: 0 0 24px;
            line-height: 1.6;
        }

        .links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        a {
            color: #6a2d22;
            text-decoration: none;
            font-weight: 700;
        }

        a:hover,
        a:focus {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="panel">
            <h1>Xác thực tài khoản</h1>
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="links">
                <a href="<?= htmlspecialchars(base_url('pages/register.php'), ENT_QUOTES, 'UTF-8') ?>">Quay lại đăng ký</a>
                <a href="<?= htmlspecialchars(base_url('pages/login.php'), ENT_QUOTES, 'UTF-8') ?>">Đi tới đăng nhập</a>
            </div>
        </section>
    </main>
</body>
</html>
