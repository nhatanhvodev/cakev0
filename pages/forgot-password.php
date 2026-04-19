<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config/connect.php';

$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['forgot_csrf_token'])) {
    $_SESSION['forgot_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || empty($_SESSION['forgot_csrf_token']) || !hash_equals($_SESSION['forgot_csrf_token'], $csrf)) {
        $message = 'Yêu cầu không hợp lệ (CSRF). Vui lòng thử lại.';
        $message_class = 'error';
    } else {
        $email_or_username = trim($_POST['email_or_username'] ?? '');
        $new_password_raw = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($email_or_username === '' || $new_password_raw === '' || $confirm_password === '') {
            $message = 'Vui lòng nhập đầy đủ thông tin.';
            $message_class = 'error';
        } elseif (strlen($new_password_raw) < 6) {
            $message = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
            $message_class = 'error';
        } elseif ($new_password_raw !== $confirm_password) {
            $message = 'Mật khẩu xác nhận không khớp.';
            $message_class = 'error';
        } else {
            $sql = "SELECT id FROM users WHERE email = ? OR username = ?";
            $stmtUser = $conn->prepare($sql);
            $stmtUser->bind_param("ss", $email_or_username, $email_or_username);
            $stmtUser->execute();
            $result = $stmtUser->get_result();
            $user = $result->fetch_assoc();
            $stmtUser->close();

            if ($user) {
                $user_id = (int) $user['id'];
                $reset_token = bin2hex(random_bytes(16));
                $new_password_hash = password_hash($new_password_raw, PASSWORD_DEFAULT);

                $stmtPending = $conn->prepare(
                    "SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1"
                );
                $stmtPending->bind_param("i", $user_id);
                $stmtPending->execute();
                $pending = $stmtPending->get_result()->fetch_assoc();
                $stmtPending->close();

                if ($pending) {
                    $request_id = (int) $pending['id'];
                    $stmtUpdate = $conn->prepare(
                        "UPDATE password_reset_requests
                         SET reset_token = ?, new_password = ?, status = 'pending', approved_at = NULL, created_at = NOW()
                         WHERE id = ?"
                    );
                    $stmtUpdate->bind_param("ssi", $reset_token, $new_password_hash, $request_id);
                    $ok = $stmtUpdate->execute();
                    $stmtUpdate->close();

                    if ($ok) {
                        $message = 'Yêu cầu đổi mật khẩu đã được cập nhật và gửi đến khu vực duyệt của admin.';
                        $message_class = 'success';
                    } else {
                        $message = 'Không thể cập nhật yêu cầu. Vui lòng thử lại.';
                        $message_class = 'error';
                    }
                } else {
                    $stmtInsert = $conn->prepare(
                        "INSERT INTO password_reset_requests (user_id, reset_token, new_password, status)
                         VALUES (?, ?, ?, 'pending')"
                    );
                    $stmtInsert->bind_param("iss", $user_id, $reset_token, $new_password_hash);
                    $ok = $stmtInsert->execute();
                    $stmtInsert->close();

                    if ($ok) {
                        $message = 'Yêu cầu đổi mật khẩu đã được gửi đến khu vực duyệt của admin.';
                        $message_class = 'success';
                    } else {
                        $message = 'Không thể gửi yêu cầu. Vui lòng thử lại.';
                        $message_class = 'error';
                    }
                }
            } else {
                $message = 'Email hoặc tên đăng nhập không tồn tại.';
                $message_class = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
  <meta charset="UTF-8">
  <title>Quên mật khẩu | Gấu Bakery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

  <style>
    :root {
      --brown-900: #3c1819;
      --brown-800: #4a1d1f;
      --brown-700: #6a2d22;
      --caramel: #f3e0be;
      --cream: #fff7ea;
      --ink: #272727;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Poppins', sans-serif;
      color: var(--ink);
      background: radial-gradient(circle at 10% 20%, #fff3da 0%, transparent 45%),
        radial-gradient(circle at 90% 10%, #fde8c6 0%, transparent 40%),
        #ffffff;
    }

    .forgot-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 16px;
    }

    .forgot-card {
      max-width: 980px;
      width: 100%;
      border-radius: 28px;
      overflow: hidden;
      background: #fff;
      border: 1px solid var(--caramel);
      box-shadow: 0 30px 80px rgba(74, 29, 31, 0.18);
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
    }

    .forgot-left {
      position: relative;
      background: linear-gradient(145deg, #3c1819, #7a4a2a);
      color: #fbedcd;
      padding: 48px 44px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .forgot-left::after {
      content: "";
      position: absolute;
      inset: 18px;
      border: 1px solid rgba(255, 237, 205, 0.25);
      border-radius: 22px;
      pointer-events: none;
    }

    .brand-tag {
      font-size: 12px;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      opacity: 0.85;
    }

    .forgot-left h2 {
      margin: 0;
      font-weight: 700;
      font-size: 28px;
    }

    .forgot-left p {
      margin: 0;
      line-height: 1.7;
      color: rgba(255, 237, 205, 0.95);
    }

    .mini-list {
      margin: 8px 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 8px;
      font-size: 14px;
    }

    .mini-list li {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .forgot-right {
      padding: 42px 36px;
      background: var(--cream);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .forgot-right h3 {
      margin: 0 0 10px;
      color: var(--brown-800);
      font-size: 26px;
      font-weight: 700;
      text-align: center;
    }

    .desc {
      margin: 0 0 22px;
      text-align: center;
      color: #675e5a;
      font-size: 14px;
      line-height: 1.55;
    }

    .msg {
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 14px;
      font-size: 13px;
      text-align: center;
    }

    .msg.success {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .msg.error {
      background: #fdecea;
      color: #b42318;
    }

    .form-group {
      margin-bottom: 14px;
    }

    .form-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
      font-size: 13px;
      color: #4a3d38;
      font-weight: 600;
    }

    .form-group input {
      width: 100%;
      padding: 12px 13px;
      border: 1px solid #e4d2b5;
      border-radius: 12px;
      font-size: 14px;
      background: #fff;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--brown-800);
      box-shadow: 0 0 0 0.2rem rgba(74, 29, 31, 0.14);
    }

    .btn-submit {
      width: 100%;
      border: none;
      border-radius: 999px;
      padding: 12px;
      font-size: 15px;
      font-weight: 600;
      color: #fbedcd;
      background: linear-gradient(135deg, #4a1d1f, #2f1415);
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      margin-top: 4px;
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 22px rgba(74, 29, 31, 0.24);
    }

    .links {
      margin-top: 16px;
      display: flex;
      justify-content: center;
      gap: 14px;
      flex-wrap: wrap;
    }

    .links a {
      color: var(--brown-800);
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
    }

    .links a:hover {
      text-decoration: underline;
    }

    @media (max-width: 920px) {
      .forgot-card {
        grid-template-columns: 1fr;
      }

      .forgot-left,
      .forgot-right {
        padding: 28px 22px;
      }

      .forgot-left h2 {
        font-size: 24px;
      }

      .forgot-left::after {
        inset: 12px;
      }
    }
  </style>
</head>

<body>

  <main class="forgot-wrapper">
    <section class="forgot-card">
      <div class="forgot-left">
        <div class="brand-tag">Gấu Bakery</div>
        <h2>Khôi phục mật khẩu an toàn</h2>
        <p>
          Yêu cầu đặt lại mật khẩu sẽ được chuyển sang khu vực duyệt của quản trị viên.
          Sau khi duyệt, mật khẩu mới của bạn mới có hiệu lực.
        </p>
        <ul class="mini-list">
          <li><i class="fa-solid fa-shield-halved"></i> Không đổi mật khẩu trực tiếp</li>
          <li><i class="fa-solid fa-user-check"></i> Cần admin xác nhận yêu cầu</li>
          <li><i class="fa-solid fa-clock"></i> Luôn ưu tiên yêu cầu mới nhất</li>
        </ul>
      </div>

      <div class="forgot-right">
        <h3>Quên mật khẩu</h3>
        <p class="desc">Nhập tài khoản và mật khẩu mới để gửi yêu cầu duyệt.</p>

        <?php if (!empty($message)): ?>
          <div class="msg <?= $message_class ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['forgot_csrf_token'] ?? '') ?>">

          <div class="form-group">
            <label><i class="fa-solid fa-user"></i> Email hoặc tên đăng nhập</label>
            <input type="text" name="email_or_username" placeholder="vd: gaubakery@gmail.com" required>
          </div>

          <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Mật khẩu mới</label>
            <input type="password" name="new_password" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
          </div>

          <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Xác nhận mật khẩu mới</label>
            <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" minlength="6" required>
          </div>

          <button class="btn-submit" type="submit">
            <i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu duyệt
          </button>
        </form>

        <div class="links">
          <a href="/cakev0/pages/login.php">Quay lại đăng nhập</a>
          <a href="/cakev0/pages/register.php">Tạo tài khoản mới</a>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <script>
    window.showToast = function (msg, type) {
      type = type || 'success';
      let config = {
        success: { bg: 'linear-gradient(135deg, #4a1d1f, #6a2d22)', icon: '✓' },
        error: { bg: 'linear-gradient(135deg, #b42318, #f04438)', icon: '✕' },
        info: { bg: 'linear-gradient(135deg, #1d4ed8, #3b82f6)', icon: 'ℹ' },
        warning: { bg: 'linear-gradient(135deg, #b45309, #f59e0b)', icon: '⚠' }
      };
      let c = config[type] || config.success;
      Toastify({
        text: c.icon + ' ' + msg,
        duration: 3500,
        close: true,
        gravity: 'top',
        position: 'right',
        style: {
          background: c.bg,
          borderRadius: '14px',
          fontFamily: "'Poppins', sans-serif",
          fontWeight: '600',
          fontSize: '14px',
          padding: '14px 20px',
          boxShadow: '0 8px 24px rgba(0,0,0,.18)',
          minWidth: '260px'
        }
      }).showToast();
    };

    <?php if (!empty($message)): ?>
      window.showToast(<?= json_encode($message) ?>, <?= json_encode($message_class) ?>);
    <?php endif; ?>
  </script>
</body>

</html>
