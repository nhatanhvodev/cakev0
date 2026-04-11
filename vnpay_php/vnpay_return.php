<?php
require_once("config.php");
require_once("../config/connect.php");

$vnp_SecureHash = $_GET['vnp_SecureHash'];
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <title>Kết quả thanh toán VNPAY</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #e8f5f1;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: #fff;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }

        h2 {
            color: #457762;
        }

        .success {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }

        .error {
            color: #dc3545;
            font-size: 60px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #ff6b9c;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 20px;
            transition: 0.3s;
        }

        .btn:hover {
            background: #ff8fb3;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php
        $toast = null;
        if ($secureHash == $vnp_SecureHash) {
            $order_id = $_GET['vnp_TxnRef'];
            if ($_GET['vnp_ResponseCode'] == '00') {
                // Thành công
                $stmt = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                echo "<div class='success'>✅</div>";
                echo "<h2>Thanh toán thành công!</h2>";
                echo "<p>Mã đơn hàng: <strong>#" . htmlspecialchars($order_id) . "</strong></p>";
                echo "<p>Số tiền: <strong>" . number_format($_GET['vnp_Amount'] / 100, 0, ',', '.') . " VNĐ</strong></p>";
                echo "<p>Đơn hàng của bạn đã được ghi nhận và chuyển trạng thái đã thanh toán.</p>";
                $toast = ['msg' => 'Thanh toán VNPAY thành công!', 'type' => 'success'];
            } else {
                // Thất bại
                $stmt = $conn->prepare("UPDATE orders SET status = 'failed' WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                echo "<div class='error'>❌</div>";
                echo "<h2>Thanh toán thất bại!</h2>";
                echo "<p>Mã lỗi: " . htmlspecialchars($_GET['vnp_ResponseCode']) . "</p>";
                echo "<p>Đơn hàng của bạn đã bị hủy hoặc thanh toán không thành công.</p>";
                $toast = ['msg' => 'Thanh toán VNPAY thất bại.', 'type' => 'error'];
            }
        } else {
            echo "<div class='error'>⚠️</div>";
            echo "<h2>Chữ ký không hợp lệ!</h2>";
            echo "<p>Hệ thống có thể đang gặp sự cố bảo mật.</p>";
            $toast = ['msg' => 'Chữ ký VNPAY không hợp lệ.', 'type' => 'error'];
        }
        ?>
        <a href="../index.php" class="btn">Quay lại trang chủ</a>
    </div>
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

        const toast = <?= json_encode($toast) ?>;
        if (toast && toast.msg) {
            window.showToast(toast.msg, toast.type);
        }
    </script>
</body>

</html>