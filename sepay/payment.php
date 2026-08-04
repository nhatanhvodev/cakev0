<?php

session_start();

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../includes/sepay_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /cakev0/pages/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order'] ?? 0);

$stmt = $conn->prepare("SELECT total_amount, status, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo 'Không tìm thấy đơn hàng.';
    exit;
}

$cfg = sepay_config();
$amount = (int) round((float) $order['total_amount']);
$qrUrl = sepay_build_qr_url($cfg, $orderId, $amount);
$content = sepay_payment_content($orderId);
$status = (string) $order['status'];
$expireTs = strtotime((string) $order['created_at']) + 15 * 60;
$remaining = max(0, $expireTs - time());
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán SePay | Gấu Bakery</title>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #3c1f1e;
            --muted: #7c6761;
            --line: #ead9d0;
            --brand: #8a3b2b;
            --brand-strong: #6a2d22;
            --paper: #fffaf4;
            --surface: #ffffff;
            --success: #23884f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: 'Poppins', sans-serif;
            color: var(--ink);
            background:
                linear-gradient(135deg, rgba(255, 247, 239, .94), rgba(249, 230, 217, .9)),
                url('/cakev0/assets/img/banner.jpg') center/cover fixed;
        }

        .payment-shell {
            width: min(920px, 100%);
            display: grid;
            grid-template-columns: minmax(260px, 380px) 1fr;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid rgba(138, 59, 43, .12);
            border-radius: 20px;
            box-shadow: 0 24px 70px rgba(60, 31, 30, .16);
        }

        .qr-panel {
            display: grid;
            place-items: center;
            padding: 32px;
            background: var(--paper);
            border-right: 1px solid var(--line);
        }

        .qr-image {
            width: min(280px, 100%);
            aspect-ratio: 1;
            object-fit: contain;
            padding: 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .detail-panel {
            padding: 34px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: clamp(26px, 4vw, 34px);
            line-height: 1.15;
        }

        .subtitle {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .payment-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 12px;
            padding: 11px 0;
            border-top: 1px solid var(--line);
            font-size: 14px;
        }

        .payment-row span:first-child {
            color: var(--muted);
        }

        .payment-row strong {
            color: var(--brand);
            overflow-wrap: anywhere;
        }

        .content-code {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 4px 10px;
            border-radius: 8px;
            background: #fff1e8;
            color: var(--brand-strong);
            font-weight: 700;
            letter-spacing: 0;
        }

        .note {
            margin: 22px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            margin-top: 20px;
            padding: 0 18px;
            border-radius: 10px;
            background: var(--brand-strong);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .state-box {
            display: none;
            text-align: center;
            padding: 44px 34px;
        }

        .state-icon {
            display: grid;
            place-items: center;
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: rgba(35, 136, 79, .12);
            color: var(--success);
            font-size: 38px;
            font-weight: 700;
        }

        @media (max-width: 720px) {
            body {
                padding: 14px;
                place-items: start center;
            }

            .payment-shell {
                grid-template-columns: 1fr;
                border-radius: 16px;
            }

            .qr-panel {
                border-right: 0;
                border-bottom: 1px solid var(--line);
                padding: 24px;
            }

            .detail-panel {
                padding: 24px;
            }

            .payment-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <main class="payment-shell" id="pendingBox">
        <section class="qr-panel" aria-label="Mã QR thanh toán">
            <img class="qr-image" src="<?= htmlspecialchars($qrUrl) ?>" alt="VietQR SePay cho đơn #<?= (int) $orderId ?>">
        </section>
        <section class="detail-panel">
            <h1>Quét mã để thanh toán</h1>
            <p class="subtitle">Đơn hàng #<?= (int) $orderId ?> sẽ tự xác nhận sau khi SePay ghi nhận giao dịch.</p>

            <div class="payment-row">
                <span>Ngân hàng</span>
                <strong><?= htmlspecialchars($cfg['bank']) ?></strong>
            </div>
            <div class="payment-row">
                <span>Số tài khoản</span>
                <strong><?= htmlspecialchars($cfg['account']) ?></strong>
            </div>
            <div class="payment-row">
                <span>Chủ tài khoản</span>
                <strong><?= htmlspecialchars($cfg['name']) ?></strong>
            </div>
            <div class="payment-row">
                <span>Nội dung</span>
                <strong><span class="content-code"><?= htmlspecialchars($content) ?></span></strong>
            </div>
            <div class="payment-row">
                <span>Số tiền</span>
                <strong><?= number_format($amount, 0, ',', '.') ?> VNĐ</strong>
            </div>

            <p class="note">
                Vui lòng chuyển đúng số tiền và nội dung <strong><?= htmlspecialchars($content) ?></strong>.
                Mã QR còn hiệu lực <strong id="countdown">--:--</strong>.
            </p>
        </section>
    </main>

    <section class="payment-shell state-box" id="paidBox" aria-live="polite">
        <div class="detail-panel">
            <div class="state-icon">✓</div>
            <h1>Thanh toán thành công</h1>
            <p class="subtitle">Đơn hàng #<?= (int) $orderId ?> đã được ghi nhận. Cảm ơn bạn đã ủng hộ Gấu Bakery.</p>
            <a class="btn" href="/cakev0/index.php">Về trang chủ</a>
        </div>
    </section>

    <section class="payment-shell state-box" id="expireBox" aria-live="polite">
        <div class="detail-panel">
            <h1>QR đã hết hạn</h1>
            <p class="subtitle">Vui lòng đặt lại đơn để lấy mã thanh toán mới.</p>
            <a class="btn" href="/cakev0/pages/cart.php">Về giỏ hàng</a>
        </div>
    </section>

    <script>
        const orderId = <?= (int) $orderId ?>;
        let remaining = <?= (int) $remaining ?>;
        const initialStatus = <?= json_encode($status) ?>;
        const pendingBox = document.getElementById('pendingBox');
        const paidBox = document.getElementById('paidBox');
        const expireBox = document.getElementById('expireBox');
        const countdown = document.getElementById('countdown');
        let timer = null;
        let poll = null;

        function stopIntervals() {
            if (timer) clearInterval(timer);
            if (poll) clearInterval(poll);
        }

        function showPaid() {
            stopIntervals();
            pendingBox.style.display = 'none';
            expireBox.style.display = 'none';
            paidBox.style.display = 'block';
        }

        function showExpired() {
            stopIntervals();
            pendingBox.style.display = 'none';
            paidBox.style.display = 'none';
            expireBox.style.display = 'block';
        }

        function renderCountdown() {
            const minutes = Math.floor(Math.max(remaining, 0) / 60);
            const seconds = Math.max(remaining, 0) % 60;
            countdown.textContent = minutes + ':' + String(seconds).padStart(2, '0');
            if (remaining <= 0) {
                showExpired();
            }
        }

        if (initialStatus === 'paid') {
            showPaid();
        } else if (remaining <= 0) {
            showExpired();
        } else {
            renderCountdown();
            timer = setInterval(function () {
                remaining -= 1;
                renderCountdown();
            }, 1000);

            poll = setInterval(function () {
                fetch('/cakev0/api/sepay/status.php?order=' + orderId, {headers: {'Accept': 'application/json'}})
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        if (data && data.status === 'paid') {
                            showPaid();
                        }
                    })
                    .catch(function () {});
            }, 4000);
        }
    </script>
</body>
</html>
