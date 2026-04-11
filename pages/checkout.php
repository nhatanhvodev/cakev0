 
<?php
/* =============================================================================
   PHẦN 1: XỬ LÝ SERVER-SIDE [Nguồn 1-7]
   ============================================================================= */
session_start();
// 1. Kết nối Database
require_once '../config/connect.php';
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
    echo "<script>window.showToast('Giỏ hàng trống! Hãy mua thêm bánh nhé.', 'success'); location='/Cake/index.php';</script>";
    exit;
}

// 5. Tính tổng tiền (Server Side Calculation)
$total = 0;
foreach ($cart as $item) {
    $total += $item['gia'] * $item['quantity'];
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
    $payment = $_POST['payment_method']; //

    if (!$name || !$phone || !$address || !$payment) {
        echo "<script>window.showToast('Vui lòng điền đầy đủ thông tin!', 'success');</script>";
    } else {
        // Bắt đầu Transaction
        $conn->begin_transaction();
        try {
            // A. Lưu vào bảng orders
            $stmt = $conn->prepare("INSERT INTO orders(user_id, recipient_name, phone, address, payment_method, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("issssd", $user_id, $name, $phone, $address, $payment, $total);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // B. Lưu chi tiết vào bảng order_items
            $stmt_item = $conn->prepare("INSERT INTO order_items(order_id, banh_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart as $item) {
                $stmt_item->bind_param("iiid", $order_id, $item['banh_id'], $item['quantity'], $item['gia']);
                $stmt_item->execute();
            }

            // C. Xóa giỏ hàng trong DB sau khi đặt thành công
            $conn->query("DELETE FROM cart WHERE user_id = $user_id");

            $conn->commit(); // Xác nhận giao dịch
            
            // Xóa session CSRF cũ
            unset($_SESSION['csrf_token']);

            // Thông báo & Chuyển trang
            if ($payment === 'VNPAY') {
                require_once "../vnpay_php/config.php";

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
                header("Location: /Cake/index.php");
                exit;
            }

        } catch (Exception $e) {
            $conn->rollback(); // Hoàn tác nếu lỗi
            $_SESSION['toast'] = ['msg' => 'Lỗi hệ thống, vui lòng thử lại!', 'type' => 'error'];
            header("Location: /Cake/pages/checkout.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Thanh toán | Gấu Bakery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* [Nguồn 8, 9] Background Pattern */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background-color: #e8f5f1;
            background-image:
                radial-gradient(circle at 10% 15%, rgba(255,255,255,.6) 0 40px, transparent 41px),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,.5) 0 35px, transparent 36px),
                url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
        }

        /* [Nguồn 10] Layout Grid */
        .checkout-wrapper { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .checkout-grid { display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 30px; }
        
        /* [Nguồn 10, 11] Box Styles & Decor */
        .checkout-box {
            background: #ffffff; padding: 30px; border-radius: 20px;
            box-shadow: 0 15px 40px rgba(69,119,98,.1);
            border: 2px solid #d9efe7; position: relative; overflow: hidden;
        }
        .checkout-box h3 { margin-top: 0; margin-bottom: 25px; color: #457762; font-size: 24px; font-weight: 700; border-bottom: 2px dashed #eee; padding-bottom: 15px; }
        
        /* Icon trang trí góc */
        .box-info::before {
            content: "<i class="fa-regular fa-clipboard" style="color: #8b4513;"></i>"; position: absolute; top: -15px; left: 20px;
            width: 50px; height: 50px; background: #ffb6c1; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* [Nguồn 12, 13] Form Inputs */
        .checkout-box label { font-weight: 600; margin-top: 15px; display: block; color: #355e4f; }
        .lb-name::before { content: "<i class="fa-regular fa-user" style="color: #8b4513;"></i> "; }
        .lb-phone::before { content: "📱 "; }
        .lb-address::before { content: "<i class="fa-solid fa-house-chimney" style="color: #8b4513;"></i> "; }
        
        .checkout-box input,
        .checkout-box textarea {
            width: 100%; padding: 12px; border-radius: 10px;
            border: 2px solid #cfe7de; margin-top: 8px; box-sizing: border-box;
            font-family: inherit; transition: .3s;
        }
        .checkout-box input:focus,
        .checkout-box textarea:focus { border-color: #457762; outline: none; }

        /* [Nguồn 14] Payment Methods */
        .payment-method { margin-top: 15px; display: flex; flex-direction: column; gap: 12px; }
        .payment-option {
            display: flex; align-items: center; padding: 12px 16px; 
            border: 2px solid #cfe7de; border-radius: 12px; cursor: pointer; transition: .2s;
        }
        .payment-option:hover { background: #f0faf6; border-color: #457762; }
        .payment-option input { width: auto; margin-right: 12px; margin-top: 0; }

        /* [Nguồn 15] Button */
        .checkout-box .btn-submit {
            width: 100%; padding: 16px; margin-top: 30px;
            background: linear-gradient(135deg, #457762, #5fae92);
            color: #fff; border: none; border-radius: 14px; font-size: 18px; font-weight: 700;
            cursor: pointer; transition: .3s; box-shadow: 0 10px 25px rgba(69,119,98,.25);
        }
        .checkout-box .btn-submit:hover { transform: translateY(-3px); background: linear-gradient(135deg, #ff6b9c, #ff8fb3); }

        /* [Nguồn 16] Order Summary List */
        .order-list { list-style: none; padding: 0; margin: 0; }
        .order-item {
            display: flex; justify-content: space-between; padding: 15px 0;
            border-bottom: 1px dashed #cfe7de; font-size: 15px;
        }
        .order-item span:first-child::before { content: "<i class="fa-solid fa-circle-notch" style="color: #ff6b9c;"></i> "; }
        .total-row {
            display: flex; justify-content: space-between; padding-top: 20px;
            font-size: 20px; color: #457762; font-weight: 700;
        }

        /* [Nguồn 16, 20] QR Box */
        .qr-box {
            display: none; margin-top: 20px; text-align: center;
            background: #f0faf6; padding: 20px; border-radius: 16px; border: 2px dashed #457762;
        }
        .qr-box img { max-width: 180px; border-radius: 10px; margin-top: 10px; }

        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<section class="checkout-wrapper">
    <div class="checkout-grid">
        
        <!-- CỘT 1: FORM THÔNG TIN -->
        <div class="checkout-box box-info">
            <h3>Thông tin giao hàng</h3>
            <form method="POST" id="checkout-form">
                <!-- Token CSRF ẩn -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <label class="lb-name">Họ tên người nhận</label>
                <input type="text" name="recipient_name" required placeholder="Ví dụ: Nguyễn Văn A">
                
                <label class="lb-phone">Số điện thoại</label>
                <input type="tel" name="phone" pattern="{10}" required placeholder="09xxxxxxxxx">
                
                <label class="lb-address">Địa chỉ giao hàng</label>
                <textarea name="address" rows="3" required placeholder="Số nhà, tên đường, phường/xã..."></textarea>
                
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
                    <p style="margin:5px 0">STK: 0123456789 - Gấu Bakery</p>
                    <p style="color:#ff6b9c; font-weight:bold;">Số tiền: <?= number_format($total, 0, ',', '.') ?> VNĐ</p>
                    <!-- Ảnh QR sinh động từ Google Charts API -->
                    <img id="qr-img" src="" alt="Mã QR thanh toán">
                    <p style="font-size:12px; color:#666; margin-top:8px;">Quét mã để thanh toán nhanh</p>
                </div>

                <button type="submit" class="btn-submit"><i class="fa-solid fa-circle-check" style="color: #2e7d32;"></i> Hoàn tất đặt hàng</button>
            </form>
        </div>

        <!-- CỘT 2: ĐƠN HÀNG -->
        <div class="checkout-box">
            <h3>Đơn hàng của bạn</h3>
            <ul class="order-list">
                <?php foreach ($cart as $item): ?>
                <li class="order-item">
                    <span><?= htmlspecialchars($item['ten_banh']) ?> <small>x<?= $item['quantity'] ?></small></span>
                    <span><?= number_format($item['gia'] * $item['quantity'], 0, ',', '.') ?>đ</span>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="total-row">
                <span>Tổng cộng:</span>
                <span style="color:#ff6b9c"><?= number_format($total, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>

    </div>
</section>

<?php include '../includes/footer.html'; ?>
```

---

### PHẦN 3: JAVASCRIPT (Chỉ xử lý sự kiện UI)

Logic JS đã được rút gọn, chỉ tập trung vào việc hiển thị QR Code, loại bỏ hoàn toàn việc can thiệp vào tính toán tiền bạc (để PHP lo).

```html

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Khai báo biến
    const qrBox = document.getElementById("qr");
    const qrImg = document.getElementById("qr-img");
    const bankRadio = document.getElementById("bank");
    const codRadio = document.getElementById("cod");
    const vnpayRadio = document.getElementById("vnpay");
    
    // Tổng tiền từ PHP (In thẳng vào JS an toàn)
    const totalAmount = <?= $total ?>; 

    // Hàm cập nhật QR Code
    function updatePaymentMethod() {
        if (bankRadio.checked) {
            qrBox.style.display = "block";
            // Tạo link QR động: Nội dung + Số tiền
            const qrData = `Thanh toan don hang GauBakery - So tien ${totalAmount}`;
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrData)}`;
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