<?php
$pageTitle = 'Về chúng tôi';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
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

.about-shell {
    max-width: 1180px;
    margin: 24px auto 60px;
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.about-hero {
    background: linear-gradient(135deg, #fff7ea, #fdf1db);
    border: 1px solid var(--caramel);
    border-radius: 30px;
    padding: 28px;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 26px;
    align-items: center;
    box-shadow: 0 24px 60px rgba(74, 29, 31, 0.12);
}

.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid var(--caramel);
    font-size: 12px;
    color: var(--brown-800);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.about-hero h1 {
    margin: 14px 0 10px;
    color: var(--brown-800);
    font-size: 30px;
}

.about-hero p {
    margin: 0 0 16px;
    line-height: 1.7;
    color: #4a4a4a;
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.hero-chip {
    border: 1px solid var(--caramel);
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    color: var(--brown-700);
    background: #fff;
}

.hero-visual {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
}

.hero-visual img {
    width: 100%;
    height: 280px;
    object-fit: cover;
    display: block;
}

.hero-float {
    position: absolute;
    background: rgba(255, 247, 234, 0.96);
    border-radius: 16px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--brown-900);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
}

.hero-float img {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    object-fit: cover;
}

.float-top { top: 18px; right: 18px; }
.float-bottom { bottom: 18px; left: 18px; }

.about-values {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.value-card {
    background: #fff;
    border-radius: 22px;
    border: 1px solid var(--caramel);
    padding: 20px;
    box-shadow: 0 16px 32px rgba(74, 29, 31, 0.08);
}

.value-card h3 {
    margin: 0 0 8px;
    color: var(--brown-800);
    font-size: 18px;
}

.value-card p {
    margin: 0;
    color: #4a4a4a;
    line-height: 1.6;
    font-size: 14px;
}

.about-story {
    display: grid;
    grid-template-columns: 0.9fr 1.1fr;
    gap: 24px;
    align-items: stretch;
}

.story-image {
    width: 100%;
    height: 100%;
    min-height: 240px;
    border-radius: 24px;
    object-fit: cover;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
}

.story-panel {
    background: #fff;
    border-radius: 26px;
    border: 1px solid var(--caramel);
    padding: 24px;
    box-shadow: 0 20px 40px rgba(74, 29, 31, 0.1);
}

.story-panel h2 {
    margin: 0 0 12px;
    color: var(--brown-800);
    font-size: 22px;
}

.story-panel ul {
    padding-left: 18px;
    margin: 0;
    color: #4a4a4a;
    line-height: 1.7;
}

.about-banner {
    background: #fff7ea;
    border: 1px solid var(--caramel);
    border-radius: 24px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.about-banner p {
    margin: 0;
    font-size: 15px;
    color: var(--brown-700);
}

.about-banner strong {
    color: var(--brown-800);
}

@media (max-width: 992px) {
    .about-hero {
        grid-template-columns: 1fr;
    }
    .hero-visual img {
        height: 220px;
    }
    .about-values {
        grid-template-columns: 1fr 1fr;
    }
    .about-story {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .about-values {
        grid-template-columns: 1fr;
    }
    .about-banner {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<main class="about-shell">
    <section class="about-hero">
        <div>
            <span class="hero-tag">Tươi mới mỗi ngày</span>
            <h1>Về Gấu Bakery</h1>
            <p>
                Chúng tôi làm bánh bằng sự tận tâm, giữ vị ngọt dịu, kết cấu mềm mịn và hương thơm đặc trưng của bơ sữa.
                Mỗi chiếc bánh là một câu chuyện nhỏ được kể bằng sự tỉ mỉ trong từng công đoạn.
            </p>
            <p>
                Từ tiệm nhỏ đến gian bếp hiện đại, Gấu Bakery vẫn giữ triết lý “ngon lành, tinh tế, đúng hẹn”.
            </p>
            <div class="hero-actions">
                <span class="hero-chip">Nguyên liệu chọn lọc</span>
                <span class="hero-chip">Làm mới mỗi ngày</span>
                <span class="hero-chip">Giao nhanh đúng hẹn</span>
            </div>
        </div>
        <div class="hero-visual">
            <img src="/Cake/assets/img/banner3.jpg" alt="Không gian Gấu Bakery">
            <div class="hero-float float-top">
                <img src="/Cake/assets/img/banhngot/i1.jpg" alt="Bánh ngọt">
                <div>
                    <div style="font-size:12px; font-weight:600;">Bánh mới</div>
                    <div style="font-size:11px; opacity:.7;">Mỗi sáng</div>
                </div>
            </div>
            <div class="hero-float float-bottom">
                <img src="/Cake/assets/img/banhkem/bk1.jpg" alt="Bánh kem">
                <div>
                    <div style="font-size:12px; font-weight:600;">Signature</div>
                    <div style="font-size:11px; opacity:.7;">Bán chạy</div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-values">
        <div class="value-card">
            <h3>Nguyên liệu chọn lọc</h3>
            <p>Ưu tiên bơ sữa chất lượng và trái cây theo mùa để đảm bảo vị ngọt thanh, không gắt.</p>
        </div>
        <div class="value-card">
            <h3>Kỹ thuật thủ công</h3>
            <p>Bánh được làm mới mỗi ngày, cân chỉnh nhiệt độ và thời gian nướng chuẩn từng dòng sản phẩm.</p>
        </div>
        <div class="value-card">
            <h3>Phục vụ tận tâm</h3>
            <p>Đặt hàng dễ dàng, giao nhanh đúng hẹn, hỗ trợ khách hàng chu đáo từ chọn bánh đến nhận bánh.</p>
        </div>
    </section>

    <section class="about-story">
        <img class="story-image" src="/Cake/assets/img/banner1.jpg" alt="Bánh mới mỗi ngày">
        <div class="story-panel">
            <h2>Hành trình vị ngọt</h2>
            <ul>
                <li>2018: Khởi đầu với những mẻ bánh thủ công đầu tiên.</li>
                <li>2021: Mở rộng dòng bánh kem và set quà tặng.</li>
                <li>2024: Chuẩn hóa quy trình giao hàng và chăm sóc khách hàng.</li>
            </ul>
        </div>
    </section>

    <section class="about-banner">
        <p><strong>Gấu Bakery</strong> luôn sẵn sàng đồng hành cùng những bữa tiệc ấm áp và khoảnh khắc ngọt ngào.</p>
        <p>Hotline: 0901 234 567 • Email: hello@gaubakery.vn</p>
    </section>
</main>

<?php include '../includes/footer.html'; ?>

</body>
</html>
