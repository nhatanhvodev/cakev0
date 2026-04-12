
<?php
/* =================================================================================
   PHẦN 1: PHP LOGIC (XỬ LÝ ĐĂNG NHẬP & BẢO MẬT)
   ================================================================================= */
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. Kết nối cơ sở dữ liệu
require_once '../config/connect.php';
//

// 2. Khởi tạo biến & CSRF Token
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
} //

// 3. Kiểm tra nếu đã đăng nhập thì chuyển hướng ngay
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: /Cake/admin/admin.php");
    } else {
        header("Location: /Cake/index.php");
    }
    exit;
} //

// 4. Xử lý khi người dùng nhấn nút Đăng nhập (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Yêu cầu không hợp lệ (Lỗi bảo mật CSRF)!';
    } else {
        // Lấy dữ liệu từ form
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']); //
        $ip          = $_SERVER['REMOTE_ADDR'];

        // Cho phép đăng nhập admin từ bảng admins và chuyển thẳng vào trang admin
        try {
            $stmt = $conn->prepare(
                "SELECT id, username, password FROM admins WHERE username = ? LIMIT 1"
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_toast'] = ['msg' => 'Đăng nhập admin thành công!', 'type' => 'success'];
                unset($_SESSION['csrf_token']);
                header("Location: /Cake/admin/admin.php");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            // Nếu không có bảng admins thì bỏ qua và xử lý như user thường.
        }

        // Fallback tài khoản admin demo
        if ($username === 'admin' && $password === 'admin123') {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = 0;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'admin';
            $_SESSION['admin_toast'] = ['msg' => 'Đăng nhập admin thành công!', 'type' => 'success'];
            unset($_SESSION['csrf_token']);
            header("Location: /Cake/admin/admin.php");
            exit;
        }

        // Truy vấn thông tin user
        try {
            $stmt = $conn->prepare(
                "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1"
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close(); //
        } catch (mysqli_sql_exception $e) {
            // Fallback when the users table does not have a role column.
            $stmt = $conn->prepare(
                "SELECT id, username, password FROM users WHERE username = ? LIMIT 1"
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close(); //
            if ($user) {
                $user['role'] = 'user';
            }
        }

        // Xác thực mật khẩu
        if ($user && password_verify($password, $user['password'])) {
            // Đăng nhập thành công -> Lưu Session
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['toast'] = ['msg' => 'Đăng nhập thành công!', 'type' => 'success'];
            unset($_SESSION['csrf_token']); //

            // Xử lý "Ghi nhớ đăng nhập" (Remember Me)
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $exp   = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $conn->prepare("INSERT INTO login_tokens(user_id, token, expiry) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user['id'], $token, $exp);
                $stmt->execute();
                $stmt->close();

                // Lưu cookie trong 30 ngày
                setcookie('login_token', $token, time() + 30 * 86400, '/', '', false, true); 
            } //,

            // Ghi log đăng nhập
            $stmt = $conn->prepare("INSERT INTO login_logs(user_id, login_time, ip_address, status) VALUES (?, NOW(), ?, 'success')");
            $stmt->bind_param("is", $user['id'], $ip);
            $stmt->execute();
            $stmt->close(); //

            // Chuyển hướng theo quyền hạn
            if ($user['role'] === 'admin') {
                header("Location: /Cake/admin/admin.php");
            } else {
                header("Location: /Cake/index.php");
            }
            exit;
        } else {
            $error_message = 'Tên đăng nhập hoặc mật khẩu không đúng!';
        }
    }
}
$conn->close();
?>

<!-- =================================================================================
   PHẦN 2: GIAO DIỆN HTML (VIEW)
   ================================================================================= -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Đăng nhập | Gấu Bakery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> <!-- -->

    <style>
        :root {
            --brown-900: #3c1819;
            --brown-800: #4a1d1f;
            --brown-700: #6a2d22;
            --caramel: #f3e0be;
            --cream: #fff7ea;
            --ink: #272727;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at 10% 20%, #fff3da 0%, transparent 45%),
                        radial-gradient(circle at 90% 10%, #fde8c6 0%, transparent 40%),
                        #ffffff;
            color: var(--ink);
            min-height: 100vh;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .login-card {
            max-width: 980px;
            width: 100%;
            border-radius: 28px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--caramel);
            box-shadow: 0 30px 80px rgba(74, 29, 31, .18);
        }

        .login-left {
            position: relative;
            background: linear-gradient(145deg, #3c1819, #7a4a2a);
            color: #fbedcd;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-height: 100%;
        }

        .login-left::after {
            content: "";
            position: absolute;
            inset: 18px;
            border: 1px solid rgba(255, 237, 205, 0.25);
            border-radius: 22px;
            pointer-events: none;
        }

        .brand-tag {
            font-size: 12px;
            letter-spacing: 0.32em;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .login-left h2 {
            font-weight: 700;
            font-size: 28px;
            margin: 0;
        }

        .login-left p {
            margin: 0;
            line-height: 1.6;
            color: rgba(255, 237, 205, 0.9);
        }

        .visual-stack {
            position: relative;
            margin-top: 10px;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .35);
            background: #2a0f10;
        }

        .visual-stack img {
            width: 100%;
            height: 240px;
            object-fit: cover;
            display: block;
            filter: saturate(1.05);
        }

        .float-card {
            position: absolute;
            background: rgba(255, 247, 234, 0.95);
            color: var(--brown-900);
            padding: 10px 12px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .25);
            animation: float 6s ease-in-out infinite;
        }

        .float-card img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
        }

        .float-one { top: 18px; right: 18px; animation-delay: .2s; }
        .float-two { bottom: 18px; left: 18px; animation-delay: 1s; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .taste-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .taste-chip {
            border: 1px solid rgba(255, 237, 205, 0.4);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            letter-spacing: 0.06em;
        }

        .login-right {
            padding: 48px 44px;
            background: var(--cream);
        }

        .login-right h3 {
            color: var(--brown-800);
            font-weight: 700;
            margin-bottom: 24px;
            text-align: center;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #e5d6bf;
            background: #fff;
        }

        .form-control:focus {
            border-color: var(--brown-800);
            box-shadow: 0 0 0 0.2rem rgba(74, 29, 31, 0.18);
        }

        .btn-login {
            background: linear-gradient(135deg, #4a1d1f, #2f1415);
            color: #fbedcd;
            border-radius: 30px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: .3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74, 29, 31, 0.22);
        }

        .links a {
            text-decoration: none;
            color: var(--brown-800);
            font-weight: 500;
            font-size: 14px;
        }

        .links a:hover { text-decoration: underline; }

        @media (max-width: 991px) {
            .login-left { padding: 36px 28px; }
            .login-right { padding: 36px 28px; }
            .visual-stack img { height: 200px; }
        }

        @media (max-width: 767px) {
            .login-left { order: 2; }
            .login-right { order: 1; }
        }

        @media (max-width: 600px) {
            .login-wrapper { padding: 24px 12px; }
            .login-card { border-radius: 20px; }
            .login-left, .login-right { padding: 26px 20px; }
            .login-left h2 { font-size: 24px; }
            .login-right h3 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="card login-card border-0">
        <div class="row g-0">
            
            <!-- ===== CỘT TRÁI: GIỚI THIỆU ===== -->
            <div class="col-md-6 login-left">
                <div class="brand-tag">GẤU BAKERY</div>
                <h2>Chào mừng trở lại</h2>
                <p>Đăng nhập để quản lý đơn hàng, cập nhật thông tin và lưu giữ những vị ngọt yêu thương.</p>

                <div class="visual-stack">
                    <img src="/Cake/assets/img/banner1.jpg" alt="Bánh ngon mỗi ngày">
                    <div class="float-card float-one">
                        <img src="/Cake/assets/uploads/banhngot/banh_69d9eb68d56c32.13741672.jpg" alt="Bánh ngọt">
                        <div>
                            <div style="font-size:12px; font-weight:600;">Bánh mới</div>
                            <div style="font-size:11px; opacity:.7;">Mỗi sáng</div>
                        </div>
                    </div>
                    <div class="float-card float-two">
                        <img src="/Cake/assets/uploads/banhkem/banh_69da05b2dce992.03049715.jpg" alt="Bánh kem">
                        <div>
                            <div style="font-size:12px; font-weight:600;">Đặc sắc</div>
                            <div style="font-size:11px; opacity:.7;">Bán chạy</div>
                        </div>
                    </div>
                </div>

                <div class="taste-row">
                    <span class="taste-chip">Vị ngọt dịu</span>
                    <span class="taste-chip">Kem sữa mềm</span>
                    <span class="taste-chip">Hương bơ thơm</span>
                </div>
            </div>

            <!-- ===== CỘT PHẢI: FORM ĐĂNG NHẬP ===== -->
            <div class="col-md-6 login-right">
                <h3><i class="fa-solid fa-user-lock"></i> Đăng nhập</h3>

                <!-- Hiển thị thông báo lỗi (nếu có) -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger text-center p-2 mb-3">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?> <!-- -->

                <form method="POST" action="">
                    <!-- CSRF Token (Bảo mật) -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <!-- Tên đăng nhập -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên đăng nhập</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <!-- Chú ý: name="username" khớp với PHP -->
                            <input type="text" name="username" class="form-control border-start-0" placeholder="Nhập username..." required>
                        </div>
                    </div> <!-- -->

                    <!-- Mật khẩu -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                        </div>
                    </div> <!-- -->

                    <!-- Ghi nhớ & Quên mật khẩu -->
                    <div class="d-flex justify-content-between align-items-center mb-4 small">
                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                            <!-- Chú ý: name="remember_me" khớp với PHP -->
                            <input type="checkbox" name="remember_me" class="form-check-input mt-0"> 
                            Ghi nhớ đăng nhập
                        </label>
                        <a href="/Cake/pages/forgot-password.php" class="text-secondary">Quên mật khẩu?</a>
                    </div> <!-- -->

                    <!-- Nút Submit -->
                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                    </button>
                </form>

                <!-- Link Đăng ký -->
                <div class="links text-center mt-4">
                    Chưa có tài khoản? <a href="/Cake/pages/register.php">Đăng ký ngay</a>
                </div>
            </div> <!-- Kết thúc Cột Phải -->
            
        </div>
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

    <?php if (!empty($error_message)): ?>
        window.showToast(<?= json_encode($error_message) ?>, 'error');
    <?php endif; ?>
</script>
</html>