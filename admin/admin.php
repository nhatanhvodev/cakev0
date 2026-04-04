<?php
/**
 * ADMIN DASHBOARD - FINAL VERSION
 */

// 1. KẾT NỐI VÀ CẤU HÌNH
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kết nối Database (Nhúng trực tiếp để đảm bảo hoạt động)
require_once '../config/connect.php';
// Hàm tạo lại CSRF Token
function regenerateCsrfToken()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($_SESSION['csrf_token'])) {
    regenerateCsrfToken();
}

// Hàm hỗ trợ set Toast Message
function setAdminToast($msg, $type = 'success')
{
    $_SESSION['admin_toast'] = ['msg' => $msg, 'type' => $type];
}

// Hàm xử lý đường dẫn ảnh (Kết hợp logic từ nguồn)
function buildImageUrl(string $relativePath): array
{
    $defaultImage = '/Cake/assets/img/no-image.jpg';
    $result = ['url' => $defaultImage];

    if (empty($relativePath))
        return $result;

    // Handle assets/ prefix if it's stored as img/ in the DB
    if (strpos($relativePath, 'assets/') === false && strpos($relativePath, 'img/') === 0) {
        $relativePath = 'assets/' . $relativePath;
    }

    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Cake/' . ltrim($relativePath, '/');
    if (file_exists($fullPath)) {
        $result['url'] = '/Cake/' . ltrim($relativePath, '/');
    }
    return $result;
}

// 2. XỬ LÝ LOGIC (POST REQUESTS)

/* --- ĐĂNG XUẤT --- */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

/* --- ĐĂNG NHẬP ADMIN --- */
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_error = 'Lỗi bảo mật CSRF!';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Kiểm tra tài khoản (Demo: admin/admin123) hoặc check DB nếu bảng users có phân quyền
        // Ở đây dùng logic DB để khớp với hệ thống
        $stmt = $conn->prepare(
            "SELECT id, password FROM admins WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();


        // Fallback: Nếu không có trong DB thì dùng tài khoản cứng để test (theo nguồn)
        if (($admin && password_verify($password, $admin['password'])) || ($username === 'admin' && $password === 'admin123')) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            setAdminToast("Đăng nhập quản trị thành công!");
            unset($_SESSION['csrf_token']); // Reset token sau khi login
            header("Location: admin.php");
            exit;
        } else {
            $login_error = 'Sai tài khoản hoặc mật khẩu!';
        }
    }
}

// Xử lý dữ liệu khi ĐÃ ĐĂNG NHẬP
if (isset($_SESSION['admin_logged_in'])) {

    // Tạo token mới nếu chưa có sau khi login
    if (empty($_SESSION['csrf_token']))
        regenerateCsrfToken();

    /* ===== UPLOAD HÌNH ẢNH SẢN PHẨM ===== */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['add_product']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        // ===== VALIDATE DỮ LIỆU =====
        $ten_banh = trim($_POST['ten_banh'] ?? '');
        $loai = $_POST['loai'] ?? '';
        $gia = isset($_POST['gia']) ? (float) $_POST['gia'] : 0;
        $feat = isset($_POST['is_featured']) ? 1 : 0;

        if ($ten_banh === '' || $loai === '' || $gia <= 0) {
            setAdminToast("Dữ liệu sản phẩm không hợp lệ", "error");
            header("Location: admin.php#products");
            exit;
        }

        /* ===== UPLOAD HÌNH ẢNH ===== */
        $hinh_anh = '';

        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === 0) {

            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/Cake/assets/img/banh{$loai}/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['hinh_anh']['name'], PATHINFO_EXTENSION));
            $allow = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allow)) {
                setAdminToast("Định dạng ảnh không hợp lệ (hỗ trợ: jpg, png, webp)", "error");
                header("Location: admin.php#products");
                exit;
            }

            $fileName = uniqid('banh_', true) . '.' . $ext;
            $targetPath = $upload_dir . $fileName;
            $hinh_anh = "assets/img/banh{$loai}/" . $fileName;

            if (!move_uploaded_file($_FILES['hinh_anh']['tmp_name'], $targetPath)) {
                setAdminToast("Không thể tải ảnh lên máy chủ", "error");
                header("Location: admin.php#products");
                exit;
            }
        }

        /* ===== INSERT DB ===== */
        $stmt = $conn->prepare(
            "INSERT INTO banh (ten_banh, loai, gia, hinh_anh, is_featured)
         VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssdsi",
            $ten_banh,
            $loai,
            $gia,
            $hinh_anh,
            $feat
        );

        $stmt->execute();

        setAdminToast("Thêm sản phẩm thành công!");
        header("Location: admin.php#products");
        exit;
    }


    /* --- XỬ LÝ ĐƠN HÀNG (Cập nhật trạng thái hàng loạt) --- */
    if (isset($_POST['update_order_statuses']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $allowed_statuses = ['pending', 'paid', 'approved', 'delivering', 'delivered', 'completed', 'cancelled', 'failed'];
        $selected = $_POST['selected_orders'] ?? [];
        $order_statuses = $_POST['order_status'] ?? [];

        if (empty($selected)) {
            setAdminToast("Vui lòng chọn ít nhất một đơn hàng", "warning");
            regenerateCsrfToken();
            header("Location: admin.php#orders");
            exit;
        }

        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updated = 0;

        foreach ($selected as $id) {
            $id = (int)$id;
            $status = $order_statuses[$id] ?? '';
            if ($id > 0 && in_array($status, $allowed_statuses, true)) {
                $stmt->bind_param("si", $status, $id);
                $stmt->execute();
                $updated++;
            }
        }

        $stmt->close();
        setAdminToast("Đã cập nhật trạng thái cho $updated đơn hàng");
        regenerateCsrfToken();
        header("Location: admin.php#orders");
        exit;
    }

    /* --- XỬ LÝ KHUYẾN MÃI --- */
    if (isset($_POST['add_promotion']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $stmt = $conn->prepare("INSERT INTO promotions (banh_id, gia_khuyen_mai, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $_POST['banh_id'], $_POST['gia_khuyen_mai'], $_POST['ngay_bat_dau'], $_POST['ngay_ket_thuc']);
        $stmt->execute();
        setAdminToast("Đã thêm khuyến mãi thành công!");
        regenerateCsrfToken();
        header("Location: admin.php#promotions");
        exit;
    }

    if (isset($_GET['delete_promotion_id'])) {
        $id = (int) $_GET['delete_promotion_id'];
        $conn->query("DELETE FROM promotions WHERE id=$id");
        setAdminToast("Đã xóa khuyến mãi thành công!");
        header("Location: admin.php#promotions");
        exit;
    }
}

// 3. LẤY DỮ LIỆU HIỂN THỊ & CHUẨN BỊ BIỂU ĐỒ
$products = [];
$users = [];
$orders = [];
$order_items = [];
$promotions = [];
$total_revenue = 0;
$pending_count = 0;
$js_dates = '[]';
$js_revenues = '[]';

if (isset($_SESSION['admin_logged_in'])) {
    if (isset($_GET['delete_product_id'])) {
        $id = (int) $_GET['delete_product_id'];

        $stmt = $conn->prepare(
            "UPDATE banh SET is_deleted = 1 WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        setAdminToast("Đã ngừng bán sản phẩm thành công!");
        header("Location: admin.php#products");
        exit;
    }
}


// Lấy dữ liệu từ DB
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Lấy đơn hàng kèm thông tin user (nếu có)
// Lưu ý: Nếu user_id null hoặc đã xóa user, vẫn nên hiện đơn hàng -> dùng LEFT JOIN
$orders = $conn->query("SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$order_items = $conn->query("SELECT oi.*, b.ten_banh FROM order_items oi LEFT JOIN banh b ON oi.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);
$promotions = $conn->query("SELECT p.*, b.ten_banh FROM promotions p JOIN banh b ON p.banh_id = b.id")->fetch_all(MYSQLI_ASSOC);

// --- LOGIC THỐNG KÊ & BIỂU ĐỒ ---
// Khởi tạo mảng doanh thu 7 ngày gần nhất = 0
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_data[$date] = 0;
}

foreach ($orders as $o) {
    $status = strtolower($o['status']);
    $is_revenue = in_array($status, ['paid', 'approved', 'delivered', 'completed'], true);

    if ($is_revenue) {
        $total_revenue += $o['total_amount'];

        // Cộng tiền vào ngày tương ứng
        $order_date = date('Y-m-d', strtotime($o['created_at']));
        if (isset($chart_data[$order_date])) {
            $chart_data[$order_date] += $o['total_amount'];
        }
    }
    if ($status === 'pending') {
        $pending_count++;
    }
}

// Chuyển dữ liệu sang JSON để JS sử dụng
$js_dates = json_encode(array_keys($chart_data));
$js_revenues = json_encode(array_values($chart_data));
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Gấu Bakery</title>

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Icons & Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --brown-900: #3c1819;
            --brown-800: #4a1d1f;
            --brown-700: #6a2d22;
            --caramel: #f3e0be;
            --cream: #fff7ea;
            --ink: #272727;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #fffaf2;
            margin: 0;
            color: var(--ink);
        }

        /* --- LOGIN STYLES --- */
        .admin-login-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #fff7ea;
        }

        .admin-login-card {
            width: 420px;
            background: #fff;
            border-radius: 26px;
            padding: 40px;
            border: 1px solid var(--caramel);
            box-shadow: 0 26px 60px rgba(74, 29, 31, 0.16);
            text-align: center;
            animation: fadeUp 0.4s ease;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-login-card .icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown-800);
            font-size: 28px;
        }

        .btn-admin-login {
            background: var(--brown-800);
            color: #fbedcd;
            border-radius: 30px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border: none;
            transition: 0.3s;
        }

        .btn-admin-login:hover {
            background: #2f1415;
            transform: translateY(-2px);
        }

        /* --- DASHBOARD STYLES --- */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #ffffff, #fff7ea);
            height: 100vh;
            position: fixed;
            padding: 20px;
            border-right: 1px solid var(--caramel);
            box-shadow: 4px 0 20px rgba(74, 29, 31, 0.06);
            z-index: 1000;
        }

        .sidebar h2 {
            color: var(--brown-800);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.5rem;
        }

        .nav-link {
            color: var(--brown-700);
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #ffffff;
            border-color: var(--caramel);
            box-shadow: 0 6px 14px rgba(74, 29, 31, 0.08);
            transform: translateX(4px);
            color: var(--brown-800);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: 0.3s;
        }

        /* Stat Cards */
        .stat-card {
            position: relative;
            background: #ffffff;
            border-radius: 18px;
            padding: 20px 22px;
            border: 1px solid var(--caramel);
            box-shadow: 0 12px 26px rgba(74, 29, 31, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            transition: transform 0.2s;
            overflow: hidden;
        }

        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(243, 224, 190, 0.55), transparent 55%);
            opacity: 0.7;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-info h5 {
            margin: 0;
            font-size: 0.9rem;
            color: #7c6b67;
        }

        .stat-info h3 {
            margin: 5px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brown-800);
        }

        .stat-icon {
            font-size: 1.7rem;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(74, 29, 31, 0.12);
        }

        .stat-icon.revenue {
            background: #fff1d6;
            color: #7a3b1d;
        }

        .stat-icon.pending {
            background: #ffe7b8;
            color: #b36b00;
        }

        .stat-icon.products {
            background: #f4e1c9;
            color: #7a4b2a;
        }

        .stat-icon.customers {
            background: #f9ead5;
            color: #6a2d22;
        }

        /* Tables & Tabs */
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .custom-table {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 12px 26px rgba(74, 29, 31, 0.08);
            overflow-x: auto;
            border: 1px solid var(--caramel);
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            background: #fff1d6;
            color: var(--brown-800);
            padding: 15px;
            text-align: left;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-green {
            background: var(--brown-800);
            color: #fbedcd;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            transition: .2s;
        }

        .btn-green:hover {
            background: #2f1415;
            color: #fbedcd;
        }

        .btn-action {
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            margin-right: 5px;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-delete:hover {
            background: #c0392b;
        }
    </style>
</head>

<body class="<?= !isset($_SESSION['admin_logged_in']) ? 'admin-login-body' : '' ?>">

    <!-- ================= TRƯỜNG HỢP 1: CHƯA ĐĂNG NHẬP (HIỆN FORM LOGIN) ================= -->
    <?php if (!isset($_SESSION['admin_logged_in'])): ?>
        <div class="admin-login-card">
            <div class="icon"><i class="fa-solid fa-user-shield"></i></div>
            <h3>Admin Login</h3>
            <p>Hệ thống quản trị Gấu Bakery</p>

            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger text-center p-2 mb-3">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="admin_login" value="1">

                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Tên đăng nhập (admin)" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Mật khẩu (admin123)" required>
                </div>
                <button type="submit" class="btn-admin-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                </button>
            </form>
            <div class="mt-4 small text-muted">&copy; <?= date('Y') ?> Gấu Bakery Admin Panel</div>
        </div>

        <!-- ================= TRƯỜNG HỢP 2: ĐÃ ĐĂNG NHẬP (HIỆN DASHBOARD) ================= -->
    <?php else: ?>

        <!-- 1. SIDEBAR -->
        <div class="sidebar">
            <h2><i class="bi bi-flower1"></i> Bánh Store</h2>
            <nav class="nav flex-column">
                <a class="nav-link active" onclick="showTab(event, 'dashboard')"><i class="bi bi-speedometer2"></i>
                    Dashboard</a>
                <a class="nav-link" onclick="showTab(event, 'orders')"><i class="bi bi-cart-check"></i> Đơn hàng</a>
                <a class="nav-link" onclick="showTab(event, 'products')"><i class="bi bi-box-seam"></i> Sản phẩm</a>
                <a class="nav-link" onclick="showTab(event, 'users')"><i class="bi bi-people"></i> Khách hàng</a>
                <a class="nav-link" onclick="showTab(event, 'promotions')"><i class="bi bi-tags"></i> Khuyến mãi</a>
            </nav>
        </div>

        <!-- 2. MAIN CONTENT -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm" style="border:1px solid #f3e0be;">
                <h3 class="m-0 fw-bold" style="color:#4a1d1f;">Quản Trị Hệ Thống</h3>
                <a href="?logout=1" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Đăng
                    xuất</a>
            </div>

            <!-- TAB 1: DASHBOARD -->
            <div id="dashboard" class="tab-content active">
                <!-- Thẻ thống kê -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng doanh thu</h5>
                                <h3><?= number_format($total_revenue, 0, ',', '.') ?>đ</h3>
                            </div>
                            <div class="stat-icon revenue"><i class="bi bi-graph-up-arrow"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Đơn chờ xử lý</h5>
                                <h3 class="text-warning"><?= $pending_count ?></h3>
                            </div>
                            <div class="stat-icon pending"><i class="bi bi-receipt"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Tổng sản phẩm</h5>
                                <h3><?= count($products) ?></h3>
                            </div>
                            <div class="stat-icon products"><i class="bi bi-box2-heart"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h5>Khách hàng</h5>
                                <h3><?= count($users) ?></h3>
                            </div>
                            <div class="stat-icon customers"><i class="bi bi-people-fill"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ doanh thu -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-3" style="border:1px solid #f3e0be;">
                            <h5 class="mb-3" style="color:#4a1d1f;"><i class="bi bi-graph-up-arrow"></i> Biểu đồ doanh thu 7 ngày qua
                            </h5>
                            <div style="height: 350px;">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bảng đơn hàng mới nhất -->
                <div class="custom-table">
                    <h5 class="mb-3">Đơn hàng mới nhất</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($orders, 0, 5) as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td><?= htmlspecialchars($o['username'] ?? 'Khách lẻ') ?></td>
                                    <td><?= number_format($o['total_amount']) ?>đ</td>
                                    <td>
                                        <?php
                                        $statusData = match (strtolower($o['status'])) {
                                            'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                            'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ'],
                                            'paid' => ['badge' => 'primary', 'label' => 'Đã thanh toán'],
                                            'approved', 'confirmed' => ['badge' => 'info', 'label' => 'Đã xác nhận'],
                                            'delivering' => ['badge' => 'info', 'label' => 'Đang giao'],
                                            'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                            'failed' => ['badge' => 'danger', 'label' => 'Thanh toán lỗi'],
                                            'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                            default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: ORDERS -->
            <div id="orders" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Quản Lý Đơn Hàng</h3>
                <div class="custom-table">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 44px;"><input type="checkbox" id="selectAllOrders"></th>
                                    <th>ID</th>
                                    <th>Khách hàng</th>
                                    <th>Chi tiết SP</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Cập nhật</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_orders[]" value="<?= $o['id'] ?>" class="order-select">
                                        </td>
                                        <td>#<?= $o['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($o['username'] ?? 'N/A') ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($o['email'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php foreach ($order_items as $i):
                                                if ($i['order_id'] == $o['id']): ?>
                                                    <div class="small">- <?= htmlspecialchars($i['ten_banh']) ?> (x<?= $i['quantity'] ?>)
                                                    </div>
                                                <?php endif; endforeach; ?>
                                        </td>
                                        <td class="fw-bold"><?= number_format($o['total_amount']) ?>đ</td>
                                        <td>
                                            <?php
                                            $statusData = match (strtolower($o['status'])) {
                                                'completed', 'thanh cong' => ['badge' => 'success', 'label' => 'Hoàn tất'],
                                                'pending', 'cho xu ly' => ['badge' => 'warning', 'label' => 'Đang chờ'],
                                                'paid' => ['badge' => 'primary', 'label' => 'Đã thanh toán'],
                                                'approved', 'confirmed' => ['badge' => 'info', 'label' => 'Đã xác nhận'],
                                                'delivering' => ['badge' => 'info', 'label' => 'Đang giao'],
                                                'delivered', 'da giao' => ['badge' => 'info', 'label' => 'Đã giao'],
                                                'failed' => ['badge' => 'danger', 'label' => 'Thanh toán lỗi'],
                                                'cancelled', 'huy' => ['badge' => 'danger', 'label' => 'Đã hủy'],
                                                default => ['badge' => 'secondary', 'label' => ucfirst($o['status'])]
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusData['badge'] ?>"><?= $statusData['label'] ?></span>
                                        </td>
                                        <td>
                                            <select name="order_status[<?= $o['id'] ?>]" class="form-select form-select-sm" style="min-width: 160px;">
                                                <?php
                                                $statusOptions = [
                                                    'pending' => 'Đang chờ',
                                                    'paid' => 'Đã thanh toán',
                                                    'approved' => 'Đã xác nhận',
                                                    'delivering' => 'Đang giao',
                                                    'delivered' => 'Đã giao',
                                                    'completed' => 'Hoàn tất',
                                                    'cancelled' => 'Đã hủy',
                                                    'failed' => 'Thanh toán lỗi'
                                                ];
                                                foreach ($statusOptions as $value => $label):
                                                    $selected = (strtolower($o['status']) === $value) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-end mt-3">
                            <button name="update_order_statuses" class="btn btn-green">
                                <i class="bi bi-check2-circle"></i> Cập nhật trạng thái đã chọn
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: PRODUCTS -->
            <div id="products" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Danh Sách Sản Phẩm</h3>
                <div class="card p-4 mb-4 border-0 shadow-sm">
                    <form method="POST" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="col-md-3">
                            <label class="form-label">Tên bánh</label>
                            <input type="text" name="ten_banh" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Loại</label>
                            <select name="loai" class="form-select">
                                <option value="ngot">Bánh ngọt</option>
                                <option value="man">Bánh mặn</option>
                                <option value="kem">Bánh kem</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Giá (VNĐ)</label>
                            <input type="number" name="gia" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hình ảnh</label>
                            <input type="file" name="hinh_anh" class="form-control" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="featCheck">
                                <label class="form-check-label" for="featCheck">Nổi bật</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button name="add_product" class="btn btn-green"><i class="bi bi-plus-circle"></i> Thêm Sản
                                Phẩm</button>
                        </div>
                    </form>
                </div>

                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên</th>
                                <th>Loại</th>
                                <th>Giá</th>
                                <th>Nổi bật</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p):
                                $img = buildImageUrl($p['hinh_anh']); ?>
                                <tr>
                                    <td><img src="<?= $img['url'] ?>" width="50" height="50" style="object-fit:cover"
                                            class="rounded"></td>
                                    <td><?= htmlspecialchars($p['ten_banh']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $p['loai'] ?></span></td>
                                    <td><?= number_format($p['gia']) ?>đ</td>
                                    <td><?= $p['is_featured'] ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td>
                                    <td>
                                        <a href="?delete_product_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Ngừng bán sản phẩm này?')">
                                            <i class="bi bi-trash"></i>
                                        </a>


                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: USERS -->
            <div id="users" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Khách Hàng</h3>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 5: PROMOTIONS -->
            <div id="promotions" class="tab-content">
                <h3 class="mb-4" style="color:#4a1d1f;">Chương Trình Khuyến Mãi</h3>
                <form method="POST" class="card p-3 border-0 shadow-sm mb-3 row g-2">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="col-md-4">
                        <select name="banh_id" class="form-select">
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['ten_banh']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="number" name="gia_khuyen_mai" class="form-control"
                            placeholder="Giá KM" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_bat_dau" class="form-control" required></div>
                    <div class="col-md-2"><input type="date" name="ngay_ket_thuc" class="form-control" required></div>
                    <div class="col-md-1"><button name="add_promotion" class="btn btn-green w-100"><i
                                class="bi bi-plus-lg"></i></button></div>
                </form>
                <div class="custom-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Giá KM</th>
                                <th>Thời gian</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($promo['ten_banh']) ?></td>
                                    <td><?= number_format($promo['gia_khuyen_mai']) ?>đ</td>
                                    <td><?= date('d/m', strtotime($promo['ngay_bat_dau'])) ?> ->
                                        <?= date('d/m', strtotime($promo['ngay_ket_thuc'])) ?>
                                    </td>
                                    <td><a href="?delete_promotion_id=<?= $promo['id'] ?>" class="text-danger"
                                            onclick="return confirm('Xóa?')"><i class="bi bi-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- End Main Content -->

        <!-- JAVASCRIPT LOGIC -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

        <script>
            // Global Toast Logic
            window.showToast = function (msg, type = 'success') {
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
                    gravity: "top",
                    position: "right",
                    stopOnFocus: true,
                    style: {
                        background: c.bg,
                        borderRadius: "14px",
                        fontFamily: "'Poppins', sans-serif",
                        fontWeight: "600",
                        fontSize: "14px",
                        padding: "14px 20px",
                        boxShadow: "0 8px 24px rgba(0,0,0,0.18)",
                        minWidth: "260px"
                    }
                }).showToast();
            };

            <?php if (isset($_SESSION['admin_toast'])): ?>
                showToast("<?= $_SESSION['admin_toast']['msg'] ?>", "<?= $_SESSION['admin_toast']['type'] ?>");
                <?php unset($_SESSION['admin_toast']); ?>
            <?php endif; ?>

            // 1. Logic chuyển Tab
            function showTab(evt, tabName) {
                // Ẩn tất cả tab
                var tabContent = document.getElementsByClassName("tab-content");
                for (var i = 0; i < tabContent.length; i++) {
                    tabContent[i].classList.remove("active");
                }
                // Bỏ active ở link sidebar
                var navLinks = document.getElementsByClassName("nav-link");
                for (var i = 0; i < navLinks.length; i++) {
                    navLinks[i].classList.remove("active");
                }
                // Hiện tab được chọn
                document.getElementById(tabName).classList.add("active");
                evt.currentTarget.classList.add("active");
            }

            document.addEventListener('DOMContentLoaded', function () {
                const selectAll = document.getElementById('selectAllOrders');
                if (!selectAll) return;
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.order-select').forEach(function (item) {
                        item.checked = selectAll.checked;
                    });
                });
            });

            // 2. Vẽ biểu đồ Chart.js (Chỉ chạy khi đã login)
            document.addEventListener("DOMContentLoaded", function () {
                const chartCanvas = document.getElementById('revenueChart');
                if (chartCanvas) {
                    const ctx = chartCanvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?= $js_dates ?>, // Dữ liệu ngày từ PHP
                            datasets: [{
                                label: 'Doanh thu (VNĐ)',
                                data: <?= $js_revenues ?>, // Dữ liệu tiền từ PHP
                                backgroundColor: 'rgba(46, 125, 50, 0.6)', // Màu xanh pastel đậm
                                borderColor: 'rgba(46, 125, 50, 1)',
                                borderWidth: 1,
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function (value) { return value.toLocaleString('vi-VN') + 'đ'; } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) { return context.raw.toLocaleString('vi-VN') + ' VNĐ'; }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>

    <?php endif; ?>
</body>

</html>
<?php $conn->close(); ?>