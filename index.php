
<?php

session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once 'config/connect.php';

$cartItems = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $sql = "
        SELECT c.*, b.ten_banh, b.hinh_anh, b.gia
        FROM cart c
        JOIN banh b ON c.banh_id = b.id
        WHERE c.user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function buildImageUrl(?string $path): string {
    if (!$path) return '/Cake/assets/img/no-image.jpg';
    if (strpos($path, 'assets/') === false && strpos($path, 'img/') === 0) {
        $path = str_replace('img/', 'assets/img/', $path);
    }
    return '/Cake/' . ltrim($path, '/');
}

function timeAgo($timestamp) {
    if (!$timestamp) return 'Không rõ thời gian';
    $time = is_numeric($timestamp) ? (int)$timestamp : strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Vừa xong';
    if ($diff < 3600) return floor($diff/60) . ' phút trước';
    if ($diff < 86400) return floor($diff/3600) . ' giờ trước';
    if ($diff < 172800) return 'Hôm qua';
    return date('d/m/Y', $time);
}

$isLoggedIn   = isset($_SESSION['user_id']);
$loggedInUser = $_SESSION['username'] ?? 'Khách';

if (isset($_POST['search_products'])) {
    header('Content-Type: application/json');
    $kw = trim($_POST['keyword']);
    $stmt = $conn->prepare("SELECT id, ten_banh, gia, hinh_anh FROM banh WHERE ten_banh LIKE ? LIMIT 5");
    $searchKw = "%$kw%";
    $stmt->bind_param("s", $searchKw);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($result as &$item) {
        $item['hinh_anh'] = buildImageUrl($item['hinh_anh']);
        $item['formatted_price'] = number_format($item['gia'], 0, ',', '.') . ' VNĐ';
    }
    
    echo json_encode(['success' => true, 'products' => $result]);
    exit;
}

if (isset($_POST['add_to_cart'])) {
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để mua hàng']);
        exit;
    }

    $banh_id = isset($_POST['banh_id']) ? (int)$_POST['banh_id'] : 0;
    $qty     = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $user_id = (int)$_SESSION['user_id'];
    
    if ($banh_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
        exit;
    }

    $sql = "INSERT INTO cart (user_id, banh_id, quantity) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống (SQL): ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("iii", $user_id, $banh_id, $qty);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '<i class="fa-solid fa-cart-shopping" style="color: #ff6b9c;"></i> Đã thêm vào giỏ hàng thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm vào giỏ: ' . $stmt->error]);
    }
    exit;
}

$slides = [
    [
        'title' => 'Tết Đoàn Viên',
        'sub'   => 'Bánh thủ công tinh túy – trọn vị yêu thương cho mùa đoàn viên.',
        'img_noi' => 'assets/img/banhngot/i6.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #fff5f5, #ffe0e0)',
        'accent' => '#ff4d4d',
        'cta' => 'Khám phá ngay'
    ],
    [
        'title' => 'Ngọt Ngào Đam Mê',
        'sub'   => 'Hương vị Sô-cô-la bản sắc cho những khoảnh khắc thăng hoa.',
        'img_noi' => 'assets/img/banhngot/i7.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #fff0f6, #ffdae9)',
        'accent' => '#ff3385',
        'cta' => 'Mua quà ngọt'
    ],
    [
        'title' => 'Tươi Mới Mỗi Ngày',
        'sub'   => 'Thưởng thức vị tươi mát từ trái cây tự nhiên trên nền kem mịn.',
        'img_noi' => 'assets/img/banhngot/i8.jpg',
        'link'  => '/Cake/pages/product.php',
        'color' => 'linear-gradient(135deg, #f0f7ff, #d9e9ff)',
        'accent' => '#3399ff',
        'cta' => 'Thử ngay'
    ]
];

$res_prod = $conn->query("SELECT * FROM banh WHERE is_featured=1 ORDER BY id DESC LIMIT 10");
$products = ($res_prod) ? $res_prod->fetch_all(MYSQLI_ASSOC) : [];

$sql_review = "SELECT * FROM reviews ORDER BY timestamp DESC LIMIT 4";
$res_review = $conn->query($sql_review);
$reviews    = ($res_review) ? $res_review->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gấu Bakery - Trang Chủ</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: #272727;
            background: #ffffff;
        }

        a { text-decoration: none; color: inherit; }

        .home-wrap {
            position: relative;
            overflow: hidden;
        }

        .hero {
            position: relative;
            padding: 40px 0 120px;
            background: #ffffff;
        }

        .hero::before {
            content: "";
            position: absolute;
            width: 1011px;
            height: 1019px;
            right: -120px;
            top: -160px;
            background: #fbedcd;
            opacity: 0.8;
            filter: blur(239.5px);
            z-index: 0;
        }

        .hero-inner {
            position: relative;
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 40px;
            align-items: center;
            z-index: 1;
        }

        .hero-title {
            font-size: 44px;
            line-height: 66px;
            font-weight: 700;
            color: #4a1d1f;
            margin: 0 0 18px;
        }

        .hero-desc {
            font-size: 24px;
            line-height: 36px;
            color: #272727;
            margin: 0 0 28px;
            max-width: 640px;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 30px;
            height: 50px;
            background: #4a1d1f;
            color: #fbedcd;
            font-size: 20px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 30px;
            height: 50px;
            border: 1px solid #4a1d1f;
            border-radius: 4px;
            font-size: 20px;
            color: #4a1d1f;
            background: transparent;
            cursor: pointer;
        }

        .hero-image {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-image img {
            width: min(520px, 100%);
            height: auto;
            transform: scaleX(-1);
        }

        .hero-strip {
            margin: 48px auto 0;
            max-width: 1180px;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .strip-shell {
            display: flex;
            align-items: center;
            gap: 26px;
            padding: 18px 26px;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            height: 201px;
        }

        .strip-thumb {
            width: 230px;
            height: 129px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 4px 19px rgba(0, 0, 0, 0.2);
        }

        .strip-track {
            display: flex;
            align-items: center;
            gap: 18px;
            overflow: hidden;
            width: 780px;
        }

        .strip-marquee {
            display: flex;
            gap: 18px;
            width: max-content;
            animation: stripMarquee 18s linear infinite;
            will-change: transform;
        }

        .strip-group {
            display: flex;
            gap: 18px;
            flex-shrink: 0;
        }

        .strip-track:hover .strip-marquee {
            animation-play-state: paused;
        }

        .strip-counter {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #4a1d1f;
            font-size: 18px;
        }

        .strip-line {
            width: 32px;
            height: 0;
            border-top: 2px solid #4a1d1f;
        }

        .strip-line.light {
            border-top-width: 1px;
        }

        @keyframes stripMarquee {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }

        .best-selling {
            max-width: 1180px;
            margin: 120px auto 80px;
            padding: 0 24px;
        }

        .best-header {
            display: flex;
            align-items: center;
            gap: 26px;
            margin-bottom: 38px;
        }

        .best-title {
            font-size: 48px;
            line-height: 72px;
            font-weight: 700;
            color: #4a1d1f;
            margin: 0;
            white-space: nowrap;
        }

        .best-divider {
            width: 128px;
            height: 0;
            border-top: 1px solid #4a1d1f;
            transform: rotate(90deg);
        }

        .best-desc {
            font-size: 22px;
            line-height: 33px;
            color: #272727;
            max-width: 720px;
            margin: 0;
        }

        .best-list {
            display: flex;
            gap: 32px;
            overflow-x: auto;
            padding-bottom: 16px;
            scroll-snap-type: x mandatory;
        }

        .best-card {
            scroll-snap-align: start;
            width: 231px;
            flex: 0 0 auto;
        }

        .best-card img {
            width: 231px;
            height: 328px;
            border-radius: 33px;
            object-fit: cover;
            box-shadow: 4px 4px 16px rgba(0, 0, 0, 0.2);
        }

        .best-name {
            margin: 14px 0 8px;
            font-size: 22px;
            line-height: 33px;
            font-weight: 400;
            color: #000000;
        }

        .best-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 16px;
            color: #707070;
        }

        .best-rating i {
            color: #ffa903;
            font-size: 18px;
        }

        .section-btn {
            margin: 44px auto 0;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 30px;
            border: 1px solid #4a1d1f;
            border-radius: 4px;
            font-size: 20px;
            color: #4a1d1f;
        }

        .section-btn span {
            display: inline-block;
            width: 29px;
            height: 0;
            border-top: 1.5px solid #4a1d1f;
        }

        .story-section {
            max-width: 1180px;
            margin: 80px auto 0;
            padding: 0 24px;
            display: grid;
            gap: 64px;
        }

        .story-row {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 44px;
            align-items: center;
        }

        .story-row.reverse {
            grid-template-columns: 0.9fr 1.1fr;
        }

        .story-title {
            font-size: 44px;
            line-height: 66px;
            font-weight: 500;
            color: #4a1d1f;
            margin: 0 0 18px;
        }

        .story-desc {
            font-size: 20px;
            line-height: 32px;
            color: #272727;
            margin: 0 0 18px;
        }

        .story-link {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            color: #4a1d1f;
        }

        .story-link span {
            width: 48px;
            height: 0;
            border-top: 1.5px solid #000000;
        }

        .story-image {
            width: 100%;
            height: 347px;
            border-radius: 47px;
            object-fit: cover;
            box-shadow: 4px 4px 20px rgba(0, 0, 0, 0.16);
        }

        .cta-section {
            background: #fbedcd;
            margin: 90px 0 0;
            padding: 70px 24px;
        }

        .cta-inner {
            max-width: 920px;
            margin: 0 auto;
            text-align: center;
        }

        .cta-title {
            font-size: 36px;
            line-height: 44px;
            font-weight: 700;
            color: #4a1d1f;
            margin: 0 0 24px;
        }

        .cta-desc {
            font-size: 20px;
            line-height: 28px;
            font-weight: 500;
            margin: 0 0 32px;
            color: #272727;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 28px;
            border-radius: 6px;
            background: #4a1d1f;
            color: #f0fdf4;
            font-size: 18px;
            font-weight: 500;
        }

        .testimonial {
            position: relative;
            padding: 90px 24px 120px;
            background: #f7f8f9;
        }

        .testimonial-inner {
            max-width: 980px;
            margin: 0 auto;
            text-align: center;
            position: relative;
        }

        .testimonial-badge {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 36px;
            background: #fbedcd;
            color: #4a1d1f;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 18px;
        }

        .testimonial-text {
            font-size: 48px;
            line-height: 60px;
            font-weight: 600;
            color: #272727;
            margin: 0;
        }

        .testimonial-dots {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 60px;
        }

        .testimonial-dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(251, 237, 205, 0.72);
        }

        .testimonial-dots span.active {
            background: #4a1d1f;
        }

        @media (max-width: 1024px) {
            .hero-inner {
                grid-template-columns: 1fr;
            }

            .hero-image {
                justify-content: flex-start;
            }

            .hero-strip {
                justify-content: center;
            }

            .best-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .best-divider {
                transform: rotate(0deg);
                width: 120px;
            }

            .story-row,
            .story-row.reverse {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 32px;
                line-height: 44px;
            }

            .hero-desc {
                font-size: 18px;
                line-height: 28px;
            }

            .best-title {
                font-size: 34px;
                line-height: 48px;
            }

            .best-desc {
                font-size: 18px;
                line-height: 28px;
            }

            .story-title {
                font-size: 32px;
                line-height: 44px;
            }

            .story-desc {
                font-size: 18px;
                line-height: 28px;
            }

            .testimonial-text {
                font-size: 28px;
                line-height: 38px;
            }
        }
    </style>
</head>
<body>

<?php
$heroImage = buildImageUrl($slides[0]['img_noi'] ?? 'assets/img/banhngot/i6.jpg');
$heroStripImages = [
    'assets/img/banhngot/i1.jpg',
    'assets/img/banhngot/i3.jpg',
    'assets/img/banhngot/i6.jpg',
    'assets/img/banhngot/i8.jpg',
    'assets/img/banhkem/b1.jpg',
    'assets/img/banhkem/b3.jpg',
    'assets/img/banhkem/b6.jpg',
    'assets/img/banhkem/b8.jpg'
];
$bestList = array_slice($products, 0, 8);
$testimonial = !empty($reviews) ? $reviews[0]['text'] : 'Bánh không trứng ở đây thực sự rất ngon. Tôi đã đặt một chiếc bánh Kit Kat và nó thực sự rất ngon. Chắc chắn đáng để thử.';
?>

<?php include 'includes/header.php'; ?>

<div class="home-wrap">
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-content">
                <h1 class="hero-title">Mang đến cho bạn niềm hạnh phúc<br>qua một miếng bánh</h1>
                <p class="hero-desc">Chúng tôi làm nhiều loại bánh khác nhau, bánh sô cô la, bánh quy mềm, bánh phô mai hoặc bất cứ thứ gì bạn muốn.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="/Cake/pages/product.php">Đặt ngay</a>
                    <a class="btn-outline" href="/Cake/pages/product.php">Xem tất cả</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="<?= htmlspecialchars($heroImage) ?>" alt="Chocolate cake">
            </div>
        </div>

        <div class="hero-strip">
            <div class="strip-shell">
                <div class="strip-counter">
                    <span class="strip-line"></span>
                    <span>Gấu Bakery</span>
                    <span class="strip-line light"></span>
                </div>
                <div class="strip-track">
                    <div class="strip-marquee">
                        <div class="strip-group">
                            <?php foreach ($heroStripImages as $imagePath): ?>
                                <img class="strip-thumb" src="<?= buildImageUrl($imagePath) ?>" alt="Bánh">
                            <?php endforeach; ?>
                        </div>
                        <div class="strip-group">
                            <?php foreach ($heroStripImages as $imagePath): ?>
                                <img class="strip-thumb" src="<?= buildImageUrl($imagePath) ?>" alt="Bánh">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="best-selling">
        <div class="best-header">
            <h2 class="best-title">Try Our Best Selling</h2>
            <span class="best-divider"></span>
            <p class="best-desc">Đây là những món ngon nhất mà mọi người đều yêu thích. Độ nhẹ và vị ngọt của bánh khiến bạn muốn ăn mãi không thôi. Hãy bắt đầu từ bánh ngọt, bánh mì và các món khác.</p>
        </div>

        <div class="best-list">
            <?php foreach ($bestList as $p): ?>
                <div class="best-card">
                    <img src="<?= buildImageUrl($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_banh']) ?>">
                    <div class="best-name"><?= htmlspecialchars($p['ten_banh']) ?> (500g)</div>
                    <div class="best-rating">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <span>4.0 Rating</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;">
            <a class="section-btn" href="/Cake/pages/product.php">Xem thêm <span></span></a>
        </div>
    </section>

    <section class="story-section">
        <div class="story-row">
            <div>
                <h3 class="story-title">Chúng tôi nướng bánh cho bạn<br>Bánh mới ra lò</h3>
                <p class="story-desc">Chúng tôi sử dụng nguyên liệu chất lượng cao được lấy trực tiếp từ nông dân. Các nhà đầu tư của chúng tôi đều là những người giàu kinh nghiệm trong lĩnh vực thực phẩm. Vì vậy, các sản phẩm chúng tôi sản xuất được đảm bảo về chất lượng và hương vị. Nó ngon đến mức bạn phải thử!</p>
                <a class="story-link" href="/Cake/pages/policy.php">Đọc thêm <span></span></a>
            </div>
            <img class="story-image" src="/Cake/assets/img/banhngot/i7.jpg" alt="Fresh baked">
        </div>

        <div class="story-row reverse">
            <img class="story-image" src="/Cake/assets/img/banhngot/i8.jpg" alt="Bakery space">
            <div>
                <h3 class="story-title">Hãy đến và chọn những món<br>bạn yêu thích nhất!</h3>
                <p class="story-desc">Hãy đến trực tiếp cửa hàng của chúng tôi để thưởng thức hương vị thơm ngon của bánh vừa mới ra lò. Vừa thưởng thức bánh cùng một tách cà phê hoặc trà trong không gian cửa hàng tiện nghi của chúng tôi. Rất thích hợp để trò chuyện, gặp gỡ đồng nghiệp.</p>
                <a class="story-link" href="/Cake/pages/shipping.php">Đọc thêm <span></span></a>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="cta-inner">
            <h3 class="cta-title">Đối với các đơn đặt bánh trên 1 KG</h3>
            <p class="cta-desc">Vui lòng ghé thăm cửa hàng gần nhất của chúng tôi hoặc gọi điện cho chúng tôi theo số 0123 456 789 (10 giờ sáng đến 7 giờ tối) để đặt hàng.</p>
            <a class="cta-btn" href="/Cake/pages/contact.php">Liên hệ với chúng tôi ngay</a>
        </div>
    </section>

    <section class="testimonial">
        <div class="testimonial-inner">
            <div class="testimonial-badge">Đánh giá</div>
            <p class="testimonial-text">“<?= htmlspecialchars($testimonial) ?>”</p>
            <div class="testimonial-dots">
                <span></span>
                <span class="active"></span>
                <span></span>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.html'; ?>
</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>