<?php
session_start();
require_once '../config/connect.php';

$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = trim($_POST['email_or_username']);
    $new_password = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);

    $sql = "SELECT id FROM users WHERE email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email_or_username, $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        $reset_token = bin2hex(random_bytes(16));

        $sql = "INSERT INTO password_reset_requests 
                (user_id, reset_token, new_password, status) 
                VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $reset_token, $new_password);
        $stmt->execute();

        $message = "Yêu cầu đặt lại mật khẩu đã được gửi. Vui lòng chờ admin duyệt.";
        $message_class = 'success';
    } else {
        $message = "Email hoặc tên đăng nhập không tồn tại.";
        $message_class = 'error';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quên mật khẩu | Gấu Bakery</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

<style>
*{box-sizing:border-box}
body{
  font-family:Poppins,sans-serif;
  min-height:100vh;
  background:linear-gradient(135deg,#e6f4f1,#fff);
  display:flex;
  align-items:center;
  justify-content:center;
}
.forgot-box{
  width:100%;
  max-width:420px;
  background:#fff;
  padding:32px;
  border-radius:20px;
  box-shadow:0 12px 30px rgba(0,0,0,.12);
}
.forgot-box h2{
  text-align:center;
  color:#457762;
  margin-bottom:10px;
}
.forgot-box p.desc{
  text-align:center;
  font-size:14px;
  color:#666;
  margin-bottom:25px;
}
.form-group{
  margin-bottom:18px;
}
.form-group label{
  font-weight:500;
  display:flex;
  align-items:center;
  gap:8px;
  margin-bottom:6px;
}
.form-group input{
  width:100%;
  padding:12px 14px;
  border-radius:12px;
  border:1.5px solid #ddd;
  font-size:15px;
}
.form-group input:focus{
  outline:none;
  border-color:#457762;
  box-shadow:0 0 0 3px rgba(69,119,98,.15);
}
.btn-submit{
  width:100%;
  padding:14px;
  border:none;
  border-radius:14px;
  background:#457762;
  color:#fff;
  font-weight:600;
  font-size:16px;
  cursor:pointer;
}
.btn-submit:hover{background:#2f5f4c}
.msg{
  padding:10px;
  border-radius:10px;
  margin-bottom:15px;
  text-align:center;
  font-size:14px;
}
.msg.success{
  background:#e8f5e9;
  color:#2e7d32;
}
.msg.error{
  background:#fdecea;
  color:#c62828;
}
.links{
  text-align:center;
  margin-top:18px;
  font-size:14px;
}
.links a{
  color:#457762;
  text-decoration:none;
}
.links a:hover{text-decoration:underline}
</style>
</head>

<body>

<div class="forgot-box">
  <h2><i class="fa-solid fa-key"></i> Quên mật khẩu</h2>
  <p class="desc">
    Nhập <b>email hoặc tên đăng nhập</b> và mật khẩu mới.<br>
    Yêu cầu sẽ được admin duyệt để đảm bảo an toàn.
  </p>

  <?php if (!empty($message)): ?>
    <div class="msg <?= $message_class ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label><i class="fa-solid fa-user"></i> Email hoặc Username</label>
      <input type="text" name="email_or_username"
             placeholder="vd: gaubakery@gmail.com"
             required>
    </div>

    <div class="form-group">
      <label><i class="fa-solid fa-lock"></i> Mật khẩu mới</label>
      <input type="password" name="new_password"
             placeholder="Tối thiểu 6 ký tự"
             required>
    </div>

    <button class="btn-submit">
      <i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu
    </button>
  </form>

  <div class="links">
    <a href="/Cake/pages/login.php">← Quay lại đăng nhập</a>
  </div>
</div>

</body>
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
</html>
