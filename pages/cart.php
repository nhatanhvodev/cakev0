<?php

session_start();
ob_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
$pageTitle = 'Giỏ hàng';

require_once '../config/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];

function ensureCartCouponsTable(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS cart_coupons (
        id INT(11) NOT NULL AUTO_INCREMENT,
        code VARCHAR(50) NOT NULL,
        discount_percent DECIMAL(5,2) NOT NULL,
        min_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        starts_at DATE DEFAULT NULL,
        ends_at DATE DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_cart_coupon_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
}

function findCartCoupon(mysqli $conn, string $couponCode, string $today): ?array {
    $stmt = $conn->prepare(
        "SELECT code, discount_percent, min_subtotal
         FROM cart_coupons
         WHERE UPPER(code) = UPPER(?)
         AND is_active = 1
         AND (starts_at IS NULL OR starts_at <= ?)
         AND (ends_at IS NULL OR ends_at >= ?)
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('sss', $couponCode, $today, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

ensureCartCouponsTable($conn);

if (isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'];

    if ($action === 'add') {
        $banh_id = (int)$_POST['banh_id'];
        $qty = max(1, (int)($_POST['qty'] ?? 1));

        if ($banh_id <= 0) {
            echo json_encode(['success' => false]);
            exit;
        }

        $check = $conn->query("
            SELECT id FROM cart 
            WHERE user_id = $user_id AND banh_id = $banh_id
        ");

        $is_new = ($check->num_rows === 0);

        if (!$is_new) {
            $conn->query("
                UPDATE cart 
                SET quantity = quantity + $qty
                WHERE user_id = $user_id AND banh_id = $banh_id
            ");
        } else {
            $conn->query("
                INSERT INTO cart (user_id, banh_id, quantity)
                VALUES ($user_id, $banh_id, $qty)
            ");
        }

        // Dếm lại số loại sản phẩm (số dòng) trong giỏ
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM cart WHERE user_id = $user_id");
        $cartCount = (int)($countRes->fetch_assoc()['cnt'] ?? 0);

        $msg = $is_new ? 'Đã thêm sản phẩm vào giỏ hàng.' : 'Đã tăng số lượng trong giỏ.';
        echo json_encode(['success' => true, 'is_new' => $is_new, 'cart_count' => $cartCount, 'message' => $msg]);
        exit;
    }
if ($action === 'add_custom') {
    $cart_id = (int)$_POST['cart_id'];
    $add_qty = max(1, (int)$_POST['add_qty']);

    $conn->query("
        UPDATE cart 
        SET quantity = quantity + $add_qty
        WHERE id = $cart_id AND user_id = $user_id
    ");

    echo json_encode(['success' => true, 'message' => 'Đã cập nhật số lượng sản phẩm.']);
    exit;
}

    $cart_id = (int)$_POST['cart_id'];

    if ($action === 'increase') {
        $conn->query("
            UPDATE cart 
            SET quantity = quantity + 1 
            WHERE id = $cart_id AND user_id = $user_id
        ");
    }

    if ($action === 'decrease') {

    $res = $conn->query("
        SELECT quantity FROM cart 
        WHERE id = $cart_id AND user_id = $user_id
    ");

    if ($res && $row = $res->fetch_assoc()) {
        if ($row['quantity'] > 1) {

            $conn->query("
                UPDATE cart 
                SET quantity = quantity - 1
                WHERE id = $cart_id AND user_id = $user_id
            ");
        } else {

            $conn->query("
                DELETE FROM cart 
                WHERE id = $cart_id AND user_id = $user_id
            ");
        }
    }
}

    if ($action === 'remove') {
        $conn->query("
            DELETE FROM cart 
            WHERE id = $cart_id AND user_id = $user_id
        ");
    }

    $actionMessages = [
        'increase' => 'Đã tăng số lượng sản phẩm.',
        'decrease' => 'Đã cập nhật số lượng sản phẩm.',
        'remove' => 'Đã xóa sản phẩm khỏi giỏ hàng.'
    ];
    echo json_encode(['success' => true, 'message' => $actionMessages[$action] ?? 'Đã cập nhật giỏ hàng.']);
    exit;
}

function buildImageUrl($path) {
    $fallback = '/cakev0/assets/img/no-image.jpg';
    if (!$path) return $fallback;

    $path = trim((string) $path);
    if ($path === '') return $fallback;

    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:image/')) {
        return $path;
    }

    $cakePos = stripos($path, '/cakev0/');
    if ($cakePos !== false) {
        $path = substr($path, $cakePos + 8);
    } else {
        $cakePos = stripos($path, 'cakev0/');
        if ($cakePos !== false) {
            $path = substr($path, $cakePos + 7);
        }
    }

    $path = ltrim($path, '/');
    if (strpos($path, 'img/') === 0 || strpos($path, 'uploads/') === 0) {
        $path = 'assets/' . $path;
    }

    return '/cakev0/' . $path;
}

$sql = "SELECT c.id AS cart_id, c.quantity, b.ten_banh, b.hinh_anh, b.gia 
        FROM cart c 
        JOIN banh b ON c.banh_id = b.id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['gia'] * $item['quantity'];
}

$couponInput = strtoupper(trim((string)($_GET['coupon'] ?? '')));
$appliedCoupon = null;
$couponError = '';
$couponSuccess = '';
$discountAmount = 0.0;
$discountPercentApplied = 0.0;
$grandTotal = (float)$subtotal;

if (!empty($cartItems) && $couponInput !== '') {
    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $couponInput)) {
        $couponError = 'Mã giảm giá không hợp lệ.';
    } else {
        $coupon = findCartCoupon($conn, $couponInput, date('Y-m-d'));
        if (!$coupon) {
            $couponError = 'Mã giảm giá không tồn tại hoặc đã hết hạn.';
        } else {
            $minSubtotal = (float)($coupon['min_subtotal'] ?? 0);
            $discountPercent = (float)($coupon['discount_percent'] ?? 0);

            if ($subtotal < $minSubtotal) {
                $couponError = 'Đơn hàng tối thiểu ' . number_format($minSubtotal, 0, ',', '.') . ' VNĐ để dùng mã này.';
            } elseif ($discountPercent <= 0) {
                $couponError = 'Mã giảm giá chưa được cấu hình hợp lệ.';
            } else {
                $discountPercentApplied = min(100, $discountPercent);
                $discountAmount = round($subtotal * ($discountPercentApplied / 100));
                $grandTotal = max(0, $subtotal - $discountAmount);
                $appliedCoupon = $coupon;
                $couponSuccess = 'Áp dụng mã ' . $coupon['code'] . ' thành công.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        
        body {
            background: #ffffff;
            color: #272727;
            font-family: 'Poppins', sans-serif;
            min-height: 100svh;
            display: flex;
            flex-direction: column;
        }

        .cart-page {
            flex: 1;
            padding: 28px 0 24px;
        }

        .cart-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 28px;
            border: 1px solid #f3e0be;
            box-shadow: 0 20px 40px rgba(74, 29, 31, 0.12);
            position: relative;
            overflow: hidden;
            margin-top: 20px;
        }

        .cart-card::before {
            content: "";
        }

        .cart-title {
            font-size: 26px; font-weight: 700; color: #4a1d1f;
            margin-bottom: 26px; display: flex; align-items: center; gap: 10px; padding-left: 20px;
        }

        table thead th { background: #fdf1db; color: #4a1d1f; font-weight: 600; }
        table tbody tr:hover { background: #fff8ee; transition: .2s; }
        table img { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }

        .qty-btn { width: 32px; height: 32px; padding: 0; border-radius: 50%; }

        .summary-box {
            background: #fdf7ef; padding: 24px;
            border-radius: 18px; border: 1.5px dashed #4a1d1f;
        }
        .summary-box h5 { font-weight: 700; color: #4a1d1f; display: flex; align-items: center; gap: 8px; }

        .cart-product-name {
            font-weight: 600;
            color: #2f1415;
            line-height: 1.45;
        }

        .cart-price-text {
            color: #5b5b5b;
            font-weight: 500;
        }

        .cart-total-text {
            color: #109c63;
            font-weight: 700;
        }

        .cart-qty-value {
            min-width: 20px;
            text-align: center;
            font-weight: 700;
            color: #2f1415;
        }

        .checkout-btn {
            background: #4a1d1f;
            border: none; box-shadow: 0 12px 24px rgba(74, 29, 31, 0.25);
        }
        .checkout-btn:hover {
            background: #2f1415; transform: translateY(-2px);
        }

        .empty-cart {
            background: #fff; padding: 60px; border-radius: 20px;
            text-align: center; box-shadow: 0 14px 30px rgba(74, 29, 31, 0.12);
        }

        @media (max-width: 768px) {
            .cart-card {
                padding: 20px;
                border-radius: 20px;
            }

            .cart-title {
                font-size: 22px;
                padding-left: 0;
            }

            table img {
                width: 54px;
            }

            .qty-btn {
                width: 28px;
                height: 28px;
            }
        }

        @media (max-width: 768px) {
            .cart-card {
                padding: 16px;
            }

            .table-responsive {
                margin: 0 -8px;
            }

            table {
                font-size: 13px;
            }

            .summary-box {
                padding: 18px;
            }

            .cart-table-wrap {
                overflow: visible;
                margin: 0;
            }

            .cart-table {
                min-width: 0;
            }

            .cart-table thead {
                display: none;
            }

            .cart-table tbody,
            .cart-table tr.cart-item-row,
            .cart-table td {
                display: block;
                width: 100%;
            }

            .cart-table tbody {
                display: grid;
                gap: 14px;
            }

            .cart-table tr.cart-item-row {
                position: relative;
                display: grid;
                grid-template-columns: 84px minmax(0, 1fr);
                gap: 12px 14px;
                align-items: start;
                padding: 16px 14px 14px;
                border: 1px solid #f3e0be;
                border-radius: 22px;
                background:
                    radial-gradient(circle at top right, rgba(251, 237, 205, 0.75), transparent 34%),
                    linear-gradient(180deg, #fffefb 0%, #fff8ee 100%);
                box-shadow: 0 14px 30px rgba(74, 29, 31, 0.1);
            }

            .cart-table tr.cart-item-row::after {
                content: "";
                position: absolute;
                left: 14px;
                right: 14px;
                top: 108px;
                bottom: 14px;
                border: 1px solid rgba(243, 224, 190, 0.9);
                border-radius: 18px;
                background: #ffffff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
                z-index: 0;
            }

            .cart-table td {
                border: 0;
                padding: 0;
                text-align: left;
                min-width: 0;
                position: relative;
                z-index: 1;
            }

            .cart-table td[data-label] {
                display: block;
            }

            .cart-table td[data-label]::before {
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #7a4a3b;
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                padding: 4px 9px;
                margin-bottom: 8px;
                border-radius: 999px;
                background: rgba(243, 224, 190, 0.75);
            }

            .cart-table .cart-remove-cell {
                position: absolute;
                top: 10px;
                right: 10px;
                width: auto;
                padding: 0;
                z-index: 3;
            }

            .cart-table .cart-remove-cell::before {
                display: none;
            }

            .cart-table .cart-remove-cell .btn-remove {
                min-width: 40px;
                width: 40px;
                height: 40px;
                border-radius: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 12px 22px rgba(220, 53, 69, 0.22);
            }

            .cart-table .cart-image-cell,
            .cart-table td:nth-child(2) {
                grid-column: 1;
                grid-row: 1 / span 2;
                align-self: start;
            }

            .cart-table .cart-image-cell img,
            .cart-table td:nth-child(2) img {
                width: 84px;
                height: 84px;
                object-fit: cover;
                border-radius: 18px;
                box-shadow: 0 10px 24px rgba(74, 29, 31, 0.12);
            }

            .cart-table .cart-product-cell,
            .cart-table td:nth-child(3) {
                grid-column: 2;
                grid-row: 1;
                padding-right: 60px;
                min-width: 0;
            }

            .cart-table .cart-price-cell,
            .cart-table td:nth-child(4),
            .cart-table .cart-total-cell,
            .cart-table td:nth-child(6),
            .cart-table .cart-qty-cell {
                background: transparent;
                border: 0;
                border-radius: 0;
                padding: 12px 12px 0;
                box-shadow: none;
            }

            .cart-table .cart-price-cell,
            .cart-table td:nth-child(4) {
                grid-column: 1 / -1;
                grid-row: 2;
            }

            .cart-table .cart-total-cell,
            .cart-table td:nth-child(6) {
                grid-column: 1 / -1;
                grid-row: 3;
            }

            .cart-table .cart-qty-cell {
                grid-column: 1 / -1;
                grid-row: 4;
                padding-bottom: 12px;
            }

            .cart-table td:nth-child(6) {
                padding-top: 10px;
                border-top: 1px solid rgba(243, 224, 190, 0.7);
            }

            .cart-table .cart-qty-cell {
                margin-top: 10px;
                border-top: 1px solid rgba(243, 224, 190, 0.7);
                padding-top: 12px;
            }

            .cart-table td:nth-child(2)::before {
                display: none;
            }

            .cart-table td:nth-child(3) {
                font-size: 18px;
                font-weight: 600;
                color: #2f1415;
                line-height: 1.45;
                padding-top: 2px;
                overflow-wrap: anywhere;
            }

            .cart-table td:nth-child(4) {
                color: #5b5b5b;
                font-weight: 500;
            }

            .cart-table td:nth-child(6) {
                color: #109c63;
                font-weight: 700;
            }

            .cart-table td:nth-child(4),
            .cart-table td:nth-child(6) {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .cart-table td:nth-child(4)::before,
            .cart-table td:nth-child(6)::before {
                margin-bottom: 0;
                flex: 0 0 auto;
            }

            .cart-table td:nth-child(4) {
                padding-top: 14px;
            }

            .cart-table td:nth-child(4),
            .cart-table td:nth-child(6),
            .cart-table .cart-price-text,
            .cart-table .cart-total-text {
                text-align: right;
            }

            .cart-table .cart-price-cell,
            .cart-table td:nth-child(4),
            .cart-table .cart-total-cell,
            .cart-table td:nth-child(6),
            .cart-table .cart-qty-cell {
                width: 100%;
            }

            .cart-table .cart-qty-cell > div {
                justify-content: space-between !important;
                width: min(180px, 100%);
                max-width: 180px;
                gap: 8px !important;
                padding: 4px;
                border-radius: 999px;
                background: #fff7ea;
                border: 1px solid rgba(243, 224, 190, 0.95);
            }

            .cart-table .cart-qty-cell span {
                min-width: 28px;
                text-align: center;
                font-weight: 700;
                color: #2f1415;
                font-size: 15px;
                line-height: 1;
            }

            .cart-table .cart-qty-cell .qty-btn {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border-color: rgba(74, 29, 31, 0.18);
                background: #fff;
                color: #6a2d22;
                box-shadow: 0 6px 14px rgba(74, 29, 31, 0.08);
            }

            .cart-table .cart-qty-cell .qty-btn:hover,
            .cart-table .cart-qty-cell .qty-btn:focus {
                background: #4a1d1f;
                border-color: #4a1d1f;
                color: #fff;
            }

            .cart-product-name {
                font-size: 18px;
            }

            .cart-price-text,
            .cart-total-text {
                font-size: 16px;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }
        }

        @media (max-width: 576px) {
            .cart-page {
                padding: 16px 0 20px;
            }

            .cart-card {
                margin-top: 12px;
                padding: 14px;
                border-radius: 18px;
            }

            .cart-title {
                font-size: 20px;
                margin-bottom: 18px;
            }

            .cart-table tbody {
                gap: 12px;
            }

            .cart-table tr.cart-item-row {
                grid-template-columns: 72px minmax(0, 1fr);
                gap: 10px 10px;
                padding: 14px 12px 12px;
                border-radius: 20px;
            }

            .cart-table tr.cart-item-row::after {
                left: 12px;
                right: 12px;
                top: 96px;
                bottom: 12px;
                border-radius: 16px;
            }

            .cart-table .cart-image-cell img,
            .cart-table td:nth-child(2) img {
                width: 72px;
                height: 72px;
            }

            .cart-table .cart-qty-cell > div {
                max-width: 154px;
                padding: 3px;
            }

            .cart-table .cart-qty-cell .qty-btn {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }

            .cart-product-name {
                font-size: 16px;
            }

            .cart-table td:nth-child(3) {
                font-size: 16px;
                padding-right: 54px;
            }

            .cart-table .cart-price-cell,
            .cart-table td:nth-child(4),
            .cart-table .cart-total-cell,
            .cart-table td:nth-child(6),
            .cart-table .cart-qty-cell {
                padding: 10px 10px 0;
            }

            .cart-table .cart-qty-cell {
                padding-bottom: 10px;
            }

            .cart-price-text,
            .cart-total-text {
                font-size: 14px;
            }

            .cart-table td:nth-child(4),
            .cart-table td:nth-child(6) {
                gap: 10px;
            }

            .cart-table .cart-qty-cell span {
                font-size: 14px;
            }

            .cart-table td[data-label]::before {
                font-size: 10px;
                min-height: 22px;
                padding: 4px 8px;
                margin-bottom: 7px;
            }

            .summary-box {
                margin-top: 4px;
                padding: 16px;
            }

            .input-group {
                flex-direction: column;
            }

            .input-group > .form-control,
            .input-group > .btn {
                width: 100%;
                border-radius: 12px !important;
            }

            .input-group > .btn + .btn,
            .input-group > .form-control + .btn,
            .input-group > .btn + .form-control {
                margin-top: 8px;
            }

            .summary-box .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: 10px;
            }

            .summary-box .d-flex .btn {
                width: 100%;
            }
        }

        .scroll-top {
            position: fixed;
            right: 20px;
            top: 80%;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: #4a1d1f;
            color: #fbedcd;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 24px rgba(74, 29, 31, 0.25);
            opacity: 0;
            visibility: hidden;
            transform: translateY(calc(-50% + 6px));
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 2000;
        }

        .scroll-top.is-visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(-50%);
        }

        .scroll-top:hover {
            background: #2f1415;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<section class="cart-page">
    <div class="container-xl">
        <div class="cart-card">
            <div class="cart-title">Giỏ hàng của bạn</div>

            <div class="table-responsive cart-table-wrap mb-4">
                <table class="table align-middle text-center cart-table">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-circle-xmark" style="color: #000000;"></i></th>
                            <th><i class="fa-regular fa-image" style="color: #000000;"></i></th>
                            <th>Sản phẩm</th>
                            <th><i class="fa-solid fa-tags" style="color: #000000;"></i> Đơn giá</th>
                            <th><i class="fa-solid fa-box-open" style="color: #000000;"></i> Số lượng</th>
                            <th><i class="fa-solid fa-money-bill-wave" style="color: #000000;"></i> Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cartItems)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-cart text-muted fs-5" style="box-shadow:none;">
                                        <div style="font-size:48px"><i class="fa-solid fa-basket-shopping" style="color: #8b4513;"></i></div>
                                        <p class="mt-3">Giỏ hàng của bạn đang trống!</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cartItems as $item): 
                                $rowTotal = $item['gia'] * $item['quantity'];
                            ?>
                            <tr class="cart-item-row">
                                
                                <td class="cart-remove-cell">
                                    <button class="btn btn-sm btn-danger btn-remove" data-id="<?= $item['cart_id'] ?>"><i class="fa-solid fa-trash-can"></i>️</button>
                                </td>
                                
                                <td class="cart-image-cell" data-label="Ảnh">
                                    <img src="<?= buildImageUrl($item['hinh_anh']) ?>" width="70" alt="Bánh">
                                </td>
                                
                                <td data-label="Sản phẩm"><?= htmlspecialchars($item['ten_banh']) ?></td>
                                
                                <td data-label="Đơn giá"><?= number_format($item['gia'], 0, ',', '.') ?> VNĐ</td>
                                
                                <td class="cart-qty-cell" data-label="Số lượng">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <button class="btn btn-sm btn-outline-secondary qty-btn btn-decrease" data-id="<?= $item['cart_id'] ?>">−</button>
                                        <span class="fw-bold" style="min-width:20px;"><?= $item['quantity'] ?></span>
                                        <button class="btn btn-sm btn-outline-secondary qty-btn btn-increase" data-id="<?= $item['cart_id'] ?>">+</button>

                                    </div>
                                </td>
                                
                                <td class="fw-bold text-success" data-label="Tổng">
                                    <?= number_format($rowTotal, 0, ',', '.') ?> VNĐ
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($cartItems)): ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <form method="get" class="mb-2">
                        <div class="input-group">
                            <input
                                type="text"
                                name="coupon"
                                class="form-control text-uppercase"
                                placeholder="Nhập mã giảm giá"
                                value="<?= htmlspecialchars($couponInput) ?>"
                                maxlength="30"
                            >
                            <button class="btn btn-outline-secondary" type="submit">Áp dụng</button>
                            <?php if ($couponInput !== ''): ?>
                                <a class="btn btn-outline-danger" href="/cakev0/pages/cart.php">Xóa mã</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($couponSuccess !== ''): ?>
                        <div class="alert alert-success py-2 mb-4" role="alert">
                            <?= htmlspecialchars($couponSuccess) ?>
                        </div>
                    <?php elseif ($couponError !== ''): ?>
                        <div class="alert alert-warning py-2 mb-4" role="alert">
                            <?= htmlspecialchars($couponError) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="summary-box">
                        <h5><i class="fa-solid fa-money-bills" style="color: #000000;"></i> Tổng giỏ hàng</h5>
                        <table class="table mb-3">
                            <tr>
                                <td>Tạm tính</td>
                                <td class="text-end"><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</td>
                            </tr>
                            <?php if ($discountAmount > 0 && $appliedCoupon): ?>
                                <tr>
                                    <td>
                                        Giảm giá (<?= htmlspecialchars($appliedCoupon['code']) ?> - <?= rtrim(rtrim(number_format($discountPercentApplied, 2, '.', ''), '0'), '.') ?>%)
                                    </td>
                                    <td class="text-end text-success">-<?= number_format($discountAmount, 0, ',', '.') ?> VNĐ</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="fw-bold">
                                <td>Tổng cộng</td>
                                <td class="text-end text-danger"><?= number_format($grandTotal, 0, ',', '.') ?> VNĐ</td>
                            </tr>
                        </table>
                        <div class="d-flex justify-content-between">
                            <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="fa-solid fa-arrows-rotate"></i> Cập nhật</button>
                            <?php
                                $checkoutUrl = '/cakev0/pages/checkout.php';
                                if ($appliedCoupon) {
                                    $checkoutUrl .= '?coupon=' . urlencode((string) $appliedCoupon['code']);
                                }
                            ?>
                            <a href="<?= htmlspecialchars($checkoutUrl) ?>" class="btn checkout-btn text-white px-4"><i class="fa-regular fa-credit-card" style="color: #ffffff;"></i> Thanh toán</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include '../includes/footer.html'; ?>

<button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    function ajaxCart(action, cartId) {

        document.body.style.cursor = 'wait';
        
        fetch('cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&cart_id=${cartId}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                if (data.message) {
                    window.showToast(data.message, 'success');
                }
                setTimeout(() => location.reload(), 600);
            } else {
                window.showToast('Có lỗi xảy ra!', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            window.showToast('Lỗi kết nối server', 'error');
        })
        .finally(() => {
            document.body.style.cursor = 'default';
        });
    }

    document.querySelectorAll('.btn-increase').forEach(btn => {
    btn.addEventListener('click', () => {
        const qtySpan = btn.parentElement.querySelector('span');
        let currentQty = parseInt(qtySpan.innerText);

        if (currentQty < 5) {
            ajaxCart('increase', btn.dataset.id);
        } else {
            let addQty = prompt('Nhập số lượng muốn thêm:', '1');
            if (addQty === null) return;

            addQty = parseInt(addQty);
            if (isNaN(addQty) || addQty <= 0) {
                window.showToast('Số lượng không hợp lệ', 'error');
                return;
            }

            let newQty = currentQty + addQty;

            qtySpan.innerText = newQty;

            fetch('cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add_custom&cart_id=${btn.dataset.id}&add_qty=${addQty}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success && d.message) {
                    window.showToast(d.message, 'success');
                }
                if (!d.success) {
                    window.showToast('Lỗi cập nhật số lượng', 'error');
                    location.reload();
                }
            });
        }
    });
});

    document.querySelectorAll('.btn-decrease').forEach(btn => {
        btn.addEventListener('click', () => {
            ajaxCart('decrease', btn.dataset.id);
        });
    });

    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            window.showConfirm('Bạn có chắc chắn muốn xóa sản phẩm này?').then(ok => { if (!ok) return; ajaxCart('remove', btn.dataset.id); })
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    if (!scrollTopBtn) return;

    const toggleScrollTop = function () {
        scrollTopBtn.classList.toggle('is-visible', window.scrollY > 300);
    };

    toggleScrollTop();
    window.addEventListener('scroll', toggleScrollTop, { passive: true });

    scrollTopBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
</body>
</html>
<?php 

if(isset($conn)) $conn->close(); 
?>
