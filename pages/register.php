
<?php
/* =================================================================================
   PHẦN 1: PHP LOGIC (Xử lý Đăng ký)
   ================================================================================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} //

require_once '../config/config.php';

$appBaseUrl = rtrim(BASE_URL, '/');
$clerkPublishableKey = (string) env_value('CLERK_PUBLISHABLE_KEY', '');
$clerkEnabled = $clerkPublishableKey !== '';

$error_message = '';

// Kiểm tra khi người dùng nhấn nút Đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/connect.php';
$conn->set_charset("utf8mb4"); //

    // Lọc dữ liệu đầu vào
    $username = trim($_POST['username']);
    $email    = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    // Validate dữ liệu
    if (!$email) {
        $error_message = "Email không hợp lệ!"; //
    } elseif (strlen($password) < 6) {
        $error_message = "Mật khẩu tối thiểu 6 ký tự!"; //
    } else {
        // Kiểm tra tên đăng nhập đã tồn tại chưa
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error_message = "Tên đăng nhập đã tồn tại!"; //
        } else {
            // Thêm người dùng mới
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)"); //
            $stmt->bind_param("sss", $username, $hash, $email); //
            
            if ($stmt->execute()) {
                // Đăng ký thành công -> Tự động đăng nhập & chuyển hướng
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['toast'] = ['msg' => 'Đăng ký thành công! Chào mừng bạn đến với Gấu Bakery.', 'type' => 'success'];
                header("Location: " . base_url('index.php'));
                exit; //
            } else {
                $error_message = "Lỗi đăng ký!";
            }
            $stmt->close();
        }
        $check->close();
    }
    $conn->close(); //
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản</title> <!-- -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Thư viện Icon & Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Bootstrap 5 (Cần thiết để chia cột col-md-6 hoạt động) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* - CSS GỐC TỪ NGUỒN */
        
        /* Reset cơ bản */
        *, *::before, *::after { box-sizing: border-box; }
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
            background: radial-gradient(circle at 12% 18%, #fff3da 0%, transparent 45%),
                        radial-gradient(circle at 90% 12%, #fde8c6 0%, transparent 40%),
                        #ffffff;
            color: var(--ink);
            min-height: 100vh;
        }

        /* Wrapper căn giữa màn hình */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center; /* */
            padding: 40px 16px;
        }

        /* Card chứa 2 cột */
        .login-card {
            max-width: 980px;
            width: 100%;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(74, 29, 31, .18);
            background: #fff; /* */
            border: 1px solid var(--caramel);
        }

        /* ===== CỘT TRÁI (LEFT) ===== */
        .login-left {
            position: relative;
            background: linear-gradient(145deg,#3c1819,#7a4a2a);
            color: #fbedcd;
            padding: 56px 46px; /* */
            display: flex;
            flex-direction: column;
            gap: 18px;
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
        .login-left h2 { font-weight: 800; font-size: 30px; letter-spacing: .3px; margin: 0; }
        .login-left p { opacity: .95; line-height: 1.8; font-size: 16px; margin: 0; } /* */
        
        /* Icons trang trí */
        .login-icons { margin-top: 10px; }
        .login-icons i {
            font-size: 30px; margin-right: 18px; padding: 14px;
            border-radius: 50%; background: rgba(255,255,255,.18);
            transition: .3s ease;
        }
        .login-icons i:hover {
            background: rgba(255,255,255,.35); transform: translateY(-3px); /* */
        }

        /* ===== CỘT PHẢI (RIGHT - FORM) ===== */
        .login-right {
            padding: 56px 48px;
            background: var(--cream); /* */
        }
        .login-right h3 {
            color: var(--brown-800); font-weight: 800; font-size: 26px;
            text-align: center; margin-bottom: 28px; /* */
        }

        /* Style cho Input Form (Đồng bộ với CSS nguồn) */
        .login-right .form-label { font-weight: 600; font-size: 14px; color: #555; margin-bottom: 8px; display: block; } /* */
        .login-right .form-control {
            width: 100%; padding: 14px 18px; border-radius: 14px;
            font-size: 15px; border: 1px solid #e5d6bf; margin-bottom: 20px; /* */
            background: #fff;
        }
        .login-right .form-control:focus {
            border-color: var(--brown-800); outline: none;
            box-shadow: 0 0 0 .15rem rgba(74,29,31,.2); /* */
        }

        /* Nút Đăng ký */
        .btn-login {
            width: 100%; background: linear-gradient(135deg, #4a1d1f, #2f1415); color: #fbedcd;
            border-radius: 30px; padding: 14px; font-weight: 700;
            font-size: 16px; border: none; transition: .3s ease; cursor: pointer; /* */
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(74, 29, 31, .22); } /* */

        .clerk-shell {
            background: #ffffff;
            border: 1px solid #e5d6bf;
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 14px;
        }

        .clerk-divider {
            text-align: center;
            margin: 8px 0 16px;
            position: relative;
            color: #7a6b59;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .clerk-divider::before,
        .clerk-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 33%;
            border-top: 1px solid #e7dac6;
        }

        .clerk-divider::before {
            left: 0;
        }

        .clerk-divider::after {
            right: 0;
        }

        /* Link chuyển trang */
        .login-right a { color: var(--brown-700); transition: .3s; text-decoration: none; }
        .login-right a:hover { color: var(--brown-800); } /* */
        
        /* Responsive Mobile */
        .visual-stack {
            position: relative;
            margin-top: 12px;
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

        @media (max-width: 991px) {
            .login-left { padding: 36px 28px; }
            .login-right { padding: 36px 28px; }
            .visual-stack img { height: 200px; }
        }

        @media (max-width: 768px) {
            .login-left { text-align: left; }
            .login-icons i { margin-bottom: 10px; } /* */
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

    <!-- ===== BỐ CỤC CHÍNH ===== -->
    <div class="login-wrapper"> <!-- -->
        <div class="card login-card border-0"> <!-- -->
            <div class="row g-0">
                
                <!-- ===== CỘT TRÁI: GIỚI THIỆU (Intro) ===== -->
                <!-- Sử dụng class col-md-6 để chiếm 50% chiều rộng -->
                <div class="col-md-6 login-left"> <!-- -->
                    <div class="brand-tag">GẤU BAKERY</div>
                    <h2 class="mb-3">Bắt đầu hành trình vị ngọt</h2> <!-- -->
                    
                    <p class="mt-2">
                        Tạo tài khoản để lưu đơn hàng, nhận ưu đãi và lưu giữ khoảnh khắc ngọt ngào cùng Gấu Bakery.
                    </p>

                    <div class="visual-stack">
                        <img src="/cakev0/assets/img/banner1.jpg" alt="Bánh ngon mỗi ngày">
                        <div class="float-card float-one">
                            <img src="/cakev0/assets/uploads/banhngot/banh_69dbab0d239db7.73922554.jpg" alt="Bánh ngọt">
                            <div>
                                <div style="font-size:12px; font-weight:600;">Bánh mới</div>
                                <div style="font-size:11px; opacity:.7;">Mỗi ngày</div>
                            </div>
                        </div>
                        <div class="float-card float-two">
                            <img src="/cakev0/assets/uploads/banhngot/banh_69dbac321327a6.77842905.jpg" alt="Bánh kem">
                            <div>
                                <div style="font-size:12px; font-weight:600;">Đặc sắc</div>
                                <div style="font-size:11px; opacity:.7;">Yêu thích</div>
                            </div>
                        </div>
                    </div>

                    <div class="taste-row">
                        <span class="taste-chip">Ưu đãi thành viên</span>
                        <span class="taste-chip">Giao nhanh</span>
                        <span class="taste-chip">Thông báo mới</span>
                    </div>
                </div>

                <!-- ===== CỘT PHẢI: FORM ĐĂNG KÝ ===== -->
                <!-- Thay vì dùng .register-card, ta dùng .col-md-6 .login-right để khớp với CSS bố cục -->
                <div class="col-md-6 login-right"> <!-- -->
                    
                    <h3><i class="fa-solid fa-user-plus"></i> Đăng ký tài khoản</h3> <!-- -->

                    <!-- Hiển thị lỗi PHP -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger text-center p-2 mb-3">
                            <?= htmlspecialchars($error_message) ?> <!-- -->
                        </div>
                    <?php endif; ?>

                    <?php if ($clerkEnabled): ?>
                        <div class="clerk-shell">
                            <div id="clerkSignUp"></div>
                        </div>
                        <div class="clerk-divider"><span>hoặc tạo tài khoản nội bộ</span></div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Input 1: Tên đăng nhập -->
                        <div>
                            <label class="form-label"><i class="fa-regular fa-user" style="color: #8b4513;"></i> Tên đăng nhập</label> <!-- -->
                            <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required> <!-- -->
                        </div>

                        <!-- Input 2: Email -->
                        <div>
                            <label class="form-label"><i class="fa-solid fa-envelope" style="color: #8b4513;"></i> Email</label> <!-- -->
                            <input type="email" name="email" class="form-control" placeholder="Ví dụ: ten@email.com" required> <!-- -->
                        </div>

                        <!-- Input 3: Mật khẩu -->
                        <div>
                            <label class="form-label"><i class="fa-solid fa-lock" style="color: #d32f2f;"></i> Mật khẩu</label> <!-- -->
                            <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự" required> <!-- -->
                        </div>

                        <!-- Nút Submit -->
                        <button type="submit" class="btn-login mt-3"> <!-- Dùng class btn-login của CSS nguồn -->
                            <i class="fa-solid fa-user-check"></i> Tạo tài khoản <!-- -->
                        </button>
                    </form>

                    <!-- Footer chuyển sang đăng nhập -->
                    <div class="text-center mt-4">
                        Đã có tài khoản? <a href="/cakev0/pages/login.php" style="font-weight: bold;">Đăng nhập ngay</a> <!-- -->
                    </div>
                </div>

            </div> <!-- End .row -->
        </div> <!-- End .login-card -->
    </div> <!-- End .login-wrapper -->

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
<?php if ($clerkEnabled): ?>
<script async crossorigin="anonymous" data-clerk-publishable-key="<?= htmlspecialchars($clerkPublishableKey, ENT_QUOTES) ?>" src="https://cdn.jsdelivr.net/npm/@clerk/clerk-js@latest/dist/clerk.browser.js"></script>
<script>
    (function () {
        const root = document.getElementById('clerkSignUp');
        if (!root) {
            return;
        }

        const appBase = <?= json_encode($appBaseUrl) ?>;
        const exchangeUrl = appBase + '/pages/clerk-session.php';
        let isExchanging = false;

        async function exchangeSession(session) {
            if (!session || isExchanging) {
                return;
            }

            isExchanging = true;

            try {
                const token = await session.getToken();
                const response = await fetch(exchangeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ token: token })
                });

                const data = await response.json().catch(function () {
                    return {};
                });

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Không thể đồng bộ phiên đăng nhập Clerk.');
                }

                window.location.href = data.redirect || (appBase + '/index.php');
            } catch (error) {
                isExchanging = false;
                window.showToast(error && error.message ? error.message : 'Đăng ký Clerk thất bại.', 'error');
            }
        }

        async function initClerk() {
            if (typeof window.Clerk === 'undefined') {
                return;
            }

            await window.Clerk.load();

            if (window.Clerk.session) {
                await exchangeSession(window.Clerk.session);
                return;
            }

            window.Clerk.mountSignUp(root, {
                signInUrl: appBase + '/pages/login.php',
                afterSignUpUrl: appBase + '/pages/register.php'
            });

            window.Clerk.addListener(function (state) {
                if (state && state.session) {
                    exchangeSession(state.session);
                }
            });
        }

        initClerk().catch(function () {
            window.showToast('Không thể khởi tạo Clerk.', 'error');
        });
    })();
</script>
<?php endif; ?>
</html>
