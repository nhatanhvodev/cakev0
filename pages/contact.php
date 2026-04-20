<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
<?php
$pageTitle = 'Liên hệ với chúng tôi';
$success_message = '';
$error_message = '';
$contactRecipient = 'hello@gaubakery.vn';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $message === '') {
        $error_message = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email không hợp lệ.';
    } else {
        $subject = 'Lien he tu Gau Bakery';
        $body = "Ho va ten: {$name}\nEmail: {$email}\nSo dien thoai: {$phone}\n\nNoi dung:\n{$message}\n";
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: Gau Bakery <no-reply@gaubakery.local>\r\n";
        $headers .= "Reply-To: {$email}\r\n";

        // Save to DB
        $stmt = $conn->prepare("INSERT INTO contact_requests (name, email, phone, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $phone, $message);
            $stmt->execute();
            $stmt->close();
        }

        if (@mail($contactRecipient, $subject, $body, $headers)) {
            $success_message = 'Cảm ơn bạn! Gấu Bakery sẽ liên hệ trong thời gian sớm nhất.';
        } else {
            $error_message = 'Gửi mail thất bại. Vui lòng thử lại sau.';
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
    <title><?= !empty($pageTitle) ? htmlspecialchars($pageTitle) . ' | Gấu Bakery' : 'Gấu Bakery' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

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
    background: #ffffff;
    color: var(--ink);
    margin: 0;
}

.contact-shell {
    max-width: 1180px;
    margin: 24px auto 60px;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 24px;
}

.contact-card {
    background: #fff;
    border-radius: 26px;
    border: 1px solid var(--caramel);
    padding: 24px;
    box-shadow: 0 22px 44px rgba(74, 29, 31, 0.12);
}

.contact-card h1 {
    margin: 0 0 12px;
    color: var(--brown-800);
    font-size: 26px;
}

.contact-card p {
    margin: 0 0 16px;
    color: #4a4a4a;
    line-height: 1.6;
}

.contact-form label {
    font-size: 13px;
    font-weight: 600;
    color: #5b4b47;
    margin-bottom: 6px;
    display: block;
}

.contact-form input,
.contact-form textarea {
    width: 100%;
    border-radius: 14px;
    border: 1px solid #e5d6bf;
    padding: 12px 14px;
    font-family: inherit;
    font-size: 14px;
    background: #fff;
}

.contact-form textarea {
    min-height: 120px;
    resize: vertical;
}

.contact-form input:focus,
.contact-form textarea:focus {
    outline: none;
    border-color: var(--brown-800);
    box-shadow: 0 0 0 0.2rem rgba(74, 29, 31, 0.18);
}

.contact-form button {
    margin-top: 12px;
    width: 100%;
    border: none;
    border-radius: 999px;
    padding: 12px 18px;
    background: linear-gradient(135deg, #4a1d1f, #2f1415);
    color: #fbedcd;
    font-weight: 600;
    transition: transform .2s ease, box-shadow .2s ease;
}

.contact-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(74, 29, 31, 0.2);
}

.contact-info {
    display: grid;
    gap: 14px;
}

.info-item {
    background: var(--cream);
    border-radius: 18px;
    border: 1px solid var(--caramel);
    padding: 16px;
}

.info-item h4 {
    margin: 0 0 6px;
    color: var(--brown-800);
    font-size: 16px;
}

.info-item p {
    margin: 0;
    font-size: 14px;
    color: #4a4a4a;
}

.contact-banner {
    width: 100%;
    height: 180px;
    border-radius: 20px;
    object-fit: cover;
    margin-top: 12px;
}

.contact-map {
    width: 100%;
    height: 220px;
    border: 0;
    border-radius: 20px;
    margin-top: 12px;
    box-shadow: 0 16px 30px rgba(74, 29, 31, 0.12);
}

@media (max-width: 992px) {
    .contact-shell {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .contact-shell {
        padding: 0 16px;
        margin: 16px auto 40px;
    }

    .contact-card {
        padding: 18px;
    }

    .contact-map {
        height: 180px;
    }

    .contact-banner {
        height: 140px;
    }
}

@media (max-width: 520px) {
    .contact-card h1 {
        font-size: 22px;
    }
}
</style>

<section class="contact-shell">
    <div class="contact-card">
        <h1>Liên hệ với chúng tôi</h1>
        <p>Hãy để lại thông tin, Gấu Bakery sẽ phản hồi sớm nhất để hỗ trợ đơn hàng hoặc tư vấn bánh phù hợp.</p>

        <script>
            <?php if ($success_message): ?>
                window.showToast(<?= json_encode($success_message) ?>, 'success');
            <?php endif; ?>
            <?php if ($error_message): ?>
                window.showToast(<?= json_encode($error_message) ?>, 'error');
            <?php endif; ?>
        </script>

        <?php if ($success_message): ?>
            <div style="background:#fdf1db; border:1px solid #f3e0be; padding:12px 14px; border-radius:14px; margin-bottom:16px;">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div style="background:#fff3f0; border:1px solid #f3c2b8; padding:12px 14px; border-radius:14px; margin-bottom:16px; color:#7a2f2a;">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form class="contact-form" method="post" action="">
            <div style="display:grid; gap:14px;">
                <div>
                    <label for="contact-name">Họ và tên</label>
                    <input id="contact-name" type="text" name="name" placeholder="Nhập họ và tên" required>
                </div>
                <div>
                    <label for="contact-email">Email</label>
                    <input id="contact-email" type="email" name="email" placeholder="Ví dụ: ten@email.com" required>
                </div>
                <div>
                    <label for="contact-phone">Số điện thoại</label>
                    <input id="contact-phone" type="tel" name="phone" placeholder="Nhập số điện thoại" required>
                </div>
                <div>
                    <label for="contact-message">Nội dung</label>
                    <textarea id="contact-message" name="message" placeholder="Bạn cần hỗ trợ gì?" required></textarea>
                </div>
            </div>
            <button type="submit">Gửi yêu cầu</button>
        </form>
    </div>

    <div class="contact-info">
        <div class="info-item">
            <h4>Địa chỉ cửa hàng</h4>
            <p>59 Mạc Đĩnh Chi, Đa Kao, Tân Định, Hồ Chí Minh 71007, Việt Nam</p>
        </div>
        <div class="info-item">
            <h4>Điện thoại</h4>
            <p>0901 234 567</p>
        </div>
        <div class="info-item">
            <h4>Email</h4>
            <p>hello@gaubakery.vn</p>
        </div>
        <div class="info-item">
            <h4>Giờ mở cửa</h4>
            <p>08:00 - 21:00 (Thứ 2 - Chủ nhật)</p>
        </div>
        <img class="contact-banner" src="/cakev0/assets/img/banner.jpg" alt="Liên hệ Gấu Bakery">
        <iframe
            class="contact-map"
            src="https://www.google.com/maps?q=59%20M%E1%BA%A1c%20%C4%90%C4%A9nh%20Chi%2C%20%C4%90a%20Kao%2C%20T%C3%A2n%20%C4%90%E1%BB%8Bnh%2C%20H%E1%BB%93%20Ch%C3%AD%20Minh%2071007%2C%20Vi%E1%BB%87t%20Nam&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Bản đồ Gấu Bakery"></iframe>
    </div>
</section>

<?php include '../includes/footer.html'; ?>

</body>
</html>
