<?php
/* =============================================================================
   PHẦN 1: XỬ LÝ SERVER-SIDE [Nguồn 1-7]
   ============================================================================= */
session_start();
// 1. Kết nối Database
require_once '../config/connect.php';
require_once '../config/coupons.php';
$pageTitle = 'Thanh toán';
$extraLinks = '<link rel="stylesheet" href="/cakev0/assets/css/style.css">';
// 2. Bảo mật: CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.showToast('Vui lòng đăng nhập để thanh toán', 'success'); location='login.php';</script>";
    exit;
}
$user_id = $_SESSION['user_id'];

ensureCartCouponInfrastructure($conn);

// 4. Lấy giỏ hàng từ Database (Ưu tiên Source thay vì LocalStorage)
$sql = "SELECT c.banh_id, b.ten_banh, b.gia, c.quantity 
        FROM cart c JOIN banh b ON c.banh_id = b.id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Nếu giỏ hàng trống
if (empty($cart)) {
    echo "<script>window.showToast('Giỏ hàng trống! Hãy mua thêm bánh nhé.', 'success'); location='/cakev0/index.php';</script>";
    exit;
}

// 5. Tính tổng tiền (Server Side Calculation)
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['gia'] * $item['quantity'];
}

$couponInputRaw = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['coupon'] ?? '')
    : ($_GET['coupon'] ?? '');
$couponInput = normalizeCouponCode((string) $couponInputRaw);
$appliedCoupon = null;
$couponError = '';
$couponSuccess = '';
$discountAmount = 0.0;
$discountPercentApplied = 0.0;
$total = (float) $subtotal;
$today = date('Y-m-d');

if ($couponInput !== '') {
    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $couponInput)) {
        $couponError = 'Mã giảm giá không hợp lệ.';
    } else {
        $coupon = findCartCoupon($conn, $couponInput, $today);
        if (!$coupon) {
            $couponError = 'Mã giảm giá không tồn tại hoặc đã hết hạn.';
        } else {
            $minSubtotal = (float) ($coupon['min_subtotal'] ?? 0);
            $discountPercent = (float) ($coupon['discount_percent'] ?? 0);
            $usageLimit = (int) ($coupon['usage_limit'] ?? 0);
            $usedCount = (int) ($coupon['used_count'] ?? 0);

            if ($subtotal < $minSubtotal) {
                $couponError = 'Đơn hàng tối thiểu ' . number_format($minSubtotal, 0, ',', '.') . ' VNĐ để dùng mã này.';
            } elseif ($usageLimit > 0 && $usedCount >= $usageLimit) {
                $couponError = 'Mã giảm giá đã hết lượt sử dụng.';
            } elseif ($discountPercent <= 0) {
                $couponError = 'Mã giảm giá chưa được cấu hình hợp lệ.';
            } else {
                $discountPercentApplied = min(100, $discountPercent);
                $discountAmount = round($subtotal * ($discountPercentApplied / 100));
                $total = max(0, $subtotal - $discountAmount);
                $appliedCoupon = $coupon;
                $couponSuccess = 'Áp dụng mã ' . $coupon['code'] . ' thành công.';
            }
        }
    }
}

// 6. XỬ LÝ KHI NGƯỜI DÙNG BẤM "THANH TOÁN" (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Lỗi bảo mật: CSRF Token không hợp lệ.");
    }

    $name    = trim($_POST['recipient_name']);
    $phone   = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $note    = trim($_POST['note'] ?? '');
    $payment = $_POST['payment_method']; //

    if (!$name || !$phone || !$address || !$payment) {
        echo "<script>window.showToast('Vui lòng điền đầy đủ thông tin!', 'success');</script>";
    } else {
        // Bắt đầu Transaction
        $conn->begin_transaction();
        try {
            // A. Lưu vào bảng orders
            $orderStatus = ($payment === 'Tiền mặt') ? 'cod_not_deposited' : 'pending';
            $couponCodeForOrder = $appliedCoupon['code'] ?? null;
            $stmt = $conn->prepare("INSERT INTO orders(user_id, recipient_name, phone, address, note, payment_method, total_amount, coupon_code, coupon_discount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssssdsds", $user_id, $name, $phone, $address, $note, $payment, $total, $couponCodeForOrder, $discountAmount, $orderStatus);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // B. Lưu chi tiết vào bảng order_items
            $stmt_item = $conn->prepare("INSERT INTO order_items(order_id, banh_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart as $item) {
                $stmt_item->bind_param("iiid", $order_id, $item['banh_id'], $item['quantity'], $item['gia']);
                $stmt_item->execute();
            }

            // C. Chỉ xóa giỏ hàng ngay với COD
            if ($payment === 'Tiền mặt') {
                $stmt_clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt_clear->bind_param("i", $user_id);
                $stmt_clear->execute();
            }

            if ($payment !== 'VNPAY' && !empty($couponCodeForOrder) && $discountAmount > 0) {
                incrementCouponUsage($conn, (string) $couponCodeForOrder);
            }

            $conn->commit(); // Xác nhận giao dịch
            
            // Xóa session CSRF cũ
            unset($_SESSION['csrf_token']);

            // Thông báo & Chuyển trang
            if ($payment === 'VNPAY') {
                require_once "../vnpay/config.php";

                $vnp_TxnRef = $order_id;
                $vnp_OrderInfo = "Thanh toan don hang Cake #" . $vnp_TxnRef;
                $vnp_OrderType = 'billpayment';
                $vnp_Amount = $total * 100;
                $vnp_Locale = 'vn';
                $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

                $inputData = array(
                    "vnp_Version" => "2.1.0",
                    "vnp_TmnCode" => $vnp_TmnCode,
                    "vnp_Amount" => $vnp_Amount,
                    "vnp_Command" => "pay",
                    "vnp_CreateDate" => date('YmdHis'),
                    "vnp_CurrCode" => "VND",
                    "vnp_IpAddr" => $vnp_IpAddr,
                    "vnp_Locale" => $vnp_Locale,
                    "vnp_OrderInfo" => $vnp_OrderInfo,
                    "vnp_OrderType" => $vnp_OrderType,
                    "vnp_ReturnUrl" => $vnp_Returnurl,
                    "vnp_TxnRef" => $vnp_TxnRef,
                    "vnp_ExpireDate" => $expire
                );

                ksort($inputData);
                $query = "";
                $i = 0;
                $hashdata = "";
                foreach ($inputData as $key => $value) {
                    if ($i == 1) {
                        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                    } else {
                        $hashdata .= urlencode($key) . "=" . urlencode($value);
                        $i = 1;
                    }
                    $query .= urlencode($key) . "=" . urlencode($value) . '&';
                }

                $vnp_Url = $vnp_Url . "?" . $query;
                if (isset($vnp_HashSecret)) {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
                }

                header('Location: ' . $vnp_Url);
                exit;
            } else {
                $toastMsg = ($payment === 'Chuyển khoản')
                    ? "Đặt hàng thành công! Vui lòng chuyển khoản để hoàn tất. Mã đơn: #$order_id"
                    : "Đặt hàng thành công! Mã đơn: #$order_id";
                $_SESSION['toast'] = ['msg' => $toastMsg, 'type' => 'success'];
                header("Location: /cakev0/index.php");
                exit;
            }

        } catch (Exception $e) {
            $conn->rollback(); // Hoàn tác nếu lỗi
            $_SESSION['toast'] = ['msg' => 'Lỗi hệ thống, vui lòng thử lại!', 'type' => 'error'];
            $redirectUrl = '/cakev0/pages/checkout.php';
            if ($couponInput !== '') {
                $redirectUrl .= '?coupon=' . urlencode($couponInput);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> | Gấu Bakery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #272727;
            --accent: #4a1d1f;
            --accent-soft: #f3e0be;
            --cream: #fff7ef;
            --card: #ffffff;
            --border: rgba(74, 29, 31, 0.15);
            --shadow: 0 18px 40px rgba(74, 29, 31, 0.12);
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: #ffffff;
            color: var(--ink);
        }

        .checkout-page {
            background: linear-gradient(180deg, #fff7ef 0%, #ffffff 55%, #fff 100%);
            padding-top: 28px;
            padding-bottom: 40px;
        }

        .checkout-hero {
            max-width: 1180px;
            margin: 0 auto 10px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .checkout-hero .eyebrow {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 700;
        }

        .checkout-hero h1 {
            margin: 6px 0 8px;
            font-size: 34px;
            color: var(--accent);
        }

        .checkout-hero p {
            margin: 0;
            color: #555;
            max-width: 520px;
        }

        .hero-badge {
            background: var(--accent);
            color: #fff7ef;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 12px 25px rgba(74, 29, 31, 0.18);
        }

        .checkout-wrapper {
            max-width: 1180px;
            margin: 20px auto 0;
            padding: 0 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
            gap: 28px;
        }

        .checkout-card {
            background: var(--card);
            padding: 28px;
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .checkout-card h3 {
            margin: 0 0 18px;
            color: var(--accent);
            font-size: 22px;
            font-weight: 700;
        }

        .checkout-card .section-note {
            margin: -8px 0 18px;
            color: #6a2d22;
            font-size: 13px;
        }

        .checkout-field label {
            font-weight: 600;
            margin-top: 16px;
            display: block;
            color: #4a1d1f;
        }

        .checkout-field input,
        .checkout-field textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-top: 8px;
            box-sizing: border-box;
            font-family: inherit;
            background: #fffaf3;
            transition: border-color .2s, box-shadow .2s;
        }

        .checkout-field input:focus,
        .checkout-field textarea:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 29, 31, 0.08);
        }

        .payment-method {
            margin-top: 12px;
            display: grid;
            gap: 12px;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 14px;
            cursor: pointer;
            transition: 0.2s ease;
            background: #fffaf3;
        }

        .payment-option:hover {
            border-color: var(--accent);
            background: #fff1e6;
        }

        .payment-option input {
            width: auto;
            margin: 0;
            accent-color: var(--accent);
        }

        .checkout-card .btn-submit {
            width: 100%;
            padding: 16px;
            margin-top: 24px;
            background: var(--accent);
            color: #fff7ef;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 12px 28px rgba(74, 29, 31, 0.2);
        }

        .checkout-card .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 35px rgba(74, 29, 31, 0.25);
        }

        .order-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 12px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed rgba(74, 29, 31, 0.2);
            font-size: 15px;
        }

        .order-item span:first-child {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .order-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            display: inline-block;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 18px;
            font-size: 20px;
            color: var(--accent);
            font-weight: 700;
        }

        .total-highlight {
            color: #c44536;
        }

        .qr-box {
            display: none;
            margin-top: 18px;
            text-align: center;
            background: #fff1e6;
            padding: 18px;
            border-radius: 16px;
            border: 1px dashed rgba(74, 29, 31, 0.3);
        }

        .qr-box img {
            max-width: 180px;
            border-radius: 12px;
            margin-top: 10px;
        }

        @media (max-width: 900px) {
            .checkout-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .checkout-card {
                padding: 20px;
                border-radius: 16px;
            }

            .checkout-hero h1 {
                font-size: 26px;
            }

            .checkout-card .btn-submit {
                font-size: 15px;
                padding: 14px;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<section class="checkout-page">
    <div class="checkout-hero">
        <div>
            <h1>Thanh toán</h1>
            <p>Hoàn tất thông tin giao hàng và chọn phương thức thanh toán phù hợp để nhận bánh nhanh nhất.</p>
        </div>
        <div class="hero-badge"><i class="fa-solid fa-shield-heart"></i> Thanh toán an toàn</div>
    </div>

    <div class="checkout-wrapper">
        <div class="checkout-grid">
        
        <!-- CỘT 1: FORM THÔNG TIN -->
        <div class="checkout-card">
            <h3>Thông tin giao hàng</h3>
            <p class="section-note">Vui lòng nhập chính xác để đơn hàng được giao đúng địa chỉ.</p>
            <form method="POST" id="checkout-form">
                <!-- Token CSRF ẩn -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <?php if ($couponInput !== ''): ?>
                    <input type="hidden" name="coupon" value="<?= htmlspecialchars($couponInput) ?>">
                <?php endif; ?>
                
                <div class="checkout-field">
                    <label>Họ tên người nhận</label>
                    <input type="text" name="recipient_name" required placeholder="Ví dụ: Nguyễn Văn A">
                </div>
                
                <div class="checkout-field">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" pattern="{10}" required placeholder="09xxxxxxxxx">
                </div>
                
                <div class="checkout-field">
                    <label>Địa chỉ giao hàng</label>
                    <textarea name="address" rows="3" required placeholder="Số nhà, tên đường, phường/xã..."></textarea>
                </div>

                <div class="checkout-field">
                    <label>Ghi chú yêu cầu thêm</label>
                    <textarea name="note" rows="3" placeholder="Ví dụ: Ghi chữ lên bánh, ngày sinh nhật..."></textarea>
                </div>

                <label>Hình thức thanh toán</label>
                <div class="payment-method">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="Tiền mặt" id="cod" checked>
                        <span><i class="fa-solid fa-truck-fast" style="color: #8b4513;"></i> Thanh toán khi nhận hàng (COD)</span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="Chuyển khoản" id="bank">
                        <span><i class="fa-regular fa-credit-card" style="color: #8b4513;"></i> Chuyển khoản ngân hàng (QR Code)</span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="VNPAY" id="vnpay">
                        <span><i class="fa-solid fa-wallet" style="color: #8b4513;"></i> Thanh toán qua VNPAY</span>
                    </label>
                </div>

                <!-- Khu vực QR Code -->
                <div class="qr-box" id="qr">
                    <p style="margin:0"><strong>Ngân hàng Vietcombank</strong></p>
                    <p style="margin:5px 0">STK: 1028944280 - VO LY NHAT ANH</p>
                    <p style="color:#c44536; font-weight:bold;">Số tiền: <?= number_format($total, 0, ',', '.') ?> VNĐ</p>
                    <img id="qr-img" src="/cakev0/assets/img/qr.jpg" alt="Mã QR thanh toán Vietcombank">
                    <p style="font-size:12px; color:#666; margin-top:8px;">Quét mã để thanh toán nhanh</p>
                </div>

                <button type="submit" class="btn-submit"><i class="fa-solid fa-circle-check" style="color: #2e7d32;"></i> Hoàn tất đặt hàng</button>
            </form>
        </div>

        <!-- CỘT 2: ĐƠN HÀNG -->
        <div class="checkout-card">
            <h3>Đơn hàng của bạn</h3>
            <p class="section-note">Kiểm tra lại món và tổng tiền trước khi xác nhận.</p>
            <?php if ($couponSuccess !== ''): ?>
                <div style="margin-bottom: 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid #b6e2c7; background: #edf9f2; color: #17653a; font-size: 13px;">
                    <?= htmlspecialchars($couponSuccess) ?>
                </div>
            <?php elseif ($couponError !== ''): ?>
                <div style="margin-bottom: 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid #f1d29a; background: #fff7e8; color: #8a5a00; font-size: 13px;">
                    <?= htmlspecialchars($couponError) ?>
                </div>
            <?php endif; ?>
            <ul class="order-list">
                <?php foreach ($cart as $item): ?>
                <li class="order-item">
                    <span><span class="order-dot"></span><?= htmlspecialchars($item['ten_banh']) ?> <small>x<?= $item['quantity'] ?></small></span>
                    <span><?= number_format($item['gia'] * $item['quantity'], 0, ',', '.') ?>đ</span>
                </li>
                <?php endforeach; ?>
            </ul>

            <div style="display:flex; justify-content:space-between; margin-top: 14px; font-size: 14px; color: #4a1d1f;">
                <span>Tạm tính:</span>
                <span><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</span>
            </div>
            <?php if ($discountAmount > 0 && $appliedCoupon): ?>
                <div style="display:flex; justify-content:space-between; margin-top: 8px; font-size: 14px; color: #2f7a43;">
                    <span>Giảm giá (<?= htmlspecialchars($appliedCoupon['code']) ?> - <?= rtrim(rtrim(number_format($discountPercentApplied, 2, '.', ''), '0'), '.') ?>%):</span>
                    <span>-<?= number_format($discountAmount, 0, ',', '.') ?> VNĐ</span>
                </div>
            <?php endif; ?>
            
            <div class="total-row">
                <span>Tổng cộng:</span>
                <span class="total-highlight"><?= number_format($total, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>

    </div>
    </div>
</section>

<?php include '../includes/footer.html'; ?>
```

---


<script>
document.addEventListener("DOMContentLoaded", () => {
    // Khai báo biến
    const qrBox = document.getElementById("qr");
    const bankRadio = document.getElementById("bank");
    const codRadio = document.getElementById("cod");
    const vnpayRadio = document.getElementById("vnpay");

    // Hàm cập nhật QR Code
    function updatePaymentMethod() {
        if (bankRadio.checked) {
            qrBox.style.display = "block";
        } else {
            qrBox.style.display = "none";
        }
    }

    // Lắng nghe sự kiện thay đổi radio button
    bankRadio.addEventListener("change", updatePaymentMethod);
    codRadio.addEventListener("change", updatePaymentMethod);
    vnpayRadio.addEventListener("change", updatePaymentMethod);
});
</script>
</body>
</html>
<?php 
// Đóng kết nối DB
if(isset($conn)) $conn->close(); 
?>
