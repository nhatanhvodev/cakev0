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
        $path = substr($path, $cakePos + 6);
    } else {
        $cakePos = stripos($path, 'cakev0/');
        if ($cakePos !== false) {
            $path = substr($path, $cakePos + 5);
        }
    }

    $path = ltrim($path, '/');
    if (strpos($path, 'img/') === 0 || strpos($path, 'uploads/') === 0) {
        $path = 'assets/' . $path;
    }

    return '/cakev0/' . $path;
}

function safeTransliterate(string $value): string {
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted === false || $converted === '') {
        return $value;
    }
    return $converted;
}

function slugify(string $value, ?int $id = null): string {
    $slug = safeTransliterate($value);
    $slug = strtolower($slug ?: $value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($id !== null) {
        $suffix = '-' . $id;
        if ($slug === '') {
            $slug = 'san-pham' . $suffix;
        } elseif (!str_ends_with($slug, $suffix)) {
            $slug .= $suffix;
        }
    }
    return $slug;
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

function normalizeStars($stars): int {
    if (is_numeric($stars)) {
        $rating = (int) $stars;
    } else {
        $rating = preg_match_all('/★/u', (string) $stars);
    }
    if ($rating < 1) return 1;
    if ($rating > 5) return 5;
    return $rating;
}

$isLoggedIn   = isset($_SESSION['user_id']);
$loggedInUser = $_SESSION['username'] ?? 'Khách';

if (isset($_POST['search_products'])) {
    header('Content-Type: application/json');
    $kw = trim($_POST['keyword']);
    if ($kw === '') {
        echo json_encode(['success' => true, 'products' => []]);
        exit;
    }

    $kw = preg_replace('/\s+/', ' ', $kw);
    $terms = array_values(array_filter(preg_split('/\s+/', $kw)));
    $normalized = [];
    foreach ($terms as $term) {
        $norm = safeTransliterate($term);
        $norm = strtolower($norm ?: $term);
        $norm = preg_replace('/[^a-z0-9]+/', '', $norm);
        $normalized[] = $norm;
    }
    $categoryMap = [
        'kem' => 'kem',
        'banhkem' => 'kem',
        'mi' => 'mi',
        'banhmi' => 'mi',
        'man' => 'man',
        'banhman' => 'man',
        'ngot' => 'ngot',
        'banhngot' => 'ngot'
    ];

    $whereParts = [];
    $categoryParts = [];
    $nameParts = [];
    $categoryParams = [];
    $nameParams = [];
    $params = [];
    $types = '';
    $termCount = count($terms);

    foreach ($normalized as $index => $term) {
        if ($termCount === 1 && isset($categoryMap[$term])) {
            $categoryParts[] = 'b.loai = ?';
            $categoryParams[] = $categoryMap[$term];
        }
        if (!empty($terms[$index])) {
            $nameParts[] = 'b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ?';
            $nameParams[] = '%' . $terms[$index] . '%';
        }
    }

    if (count($nameParts) > 1) {
        $whereParts[] = '(' . implode(' AND ', $nameParts) . ')';
        $params = array_merge($params, $nameParams);
    } elseif (count($nameParts) === 1) {
        $whereParts[] = $nameParts[0];
        $params = array_merge($params, $nameParams);
    }

    if ($termCount === 1 && !empty($categoryParts)) {
        $whereParts = array_merge($whereParts, $categoryParts);
        $params = array_merge($params, $categoryParams);
    }

    if (empty($whereParts)) {
        $whereParts[] = 'b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ?';
        $params[] = '%' . $kw . '%';
    }

    $types .= str_repeat('s', count($params));

    $sql = "SELECT b.id, b.ten_banh, b.gia, b.hinh_anh, b.slug
            FROM banh b
            WHERE " . implode(' OR ', $whereParts) . "
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($result as &$item) {
        $item['hinh_anh'] = buildImageUrl($item['hinh_anh']);
        $item['formatted_price'] = number_format($item['gia'], 0, ',', '.') . ' VNĐ';
        if (empty($item['slug'])) {
            $item['slug'] = slugify($item['ten_banh'], (int) $item['id']);
        }
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

if (isset($_POST['submit_testimonial'])) {
    $name = trim($_POST['review_name'] ?? '');
    $rating = isset($_POST['review_rating']) ? (int) $_POST['review_rating'] : 0;
    $text = trim($_POST['review_text'] ?? '');
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    if ($name === '' || $text === '' || $rating < 1 || $rating > 5) {
        $_SESSION['testimonial_flash'] = 'Vui lòng nhập đầy đủ thông tin đánh giá.';
        $_SESSION['testimonial_flash_type'] = 'error';
        header('Location: /cakev0/index.php#testimonial');
        exit;
    }

    $stars = str_repeat('★', $rating);
    $stmt = $conn->prepare(
        "INSERT INTO reviews (name, text, stars, user_id, status, timestamp)
         VALUES (?, ?, ?, ?, 'pending', ?)"
    );
    $timestamp = (int) (microtime(true) * 1000);
    $stmt->bind_param('sssii', $name, $text, $stars, $userId, $timestamp);

    if ($stmt->execute()) {
        $_SESSION['testimonial_flash'] = 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.';
        $_SESSION['testimonial_flash_type'] = 'success';
    } else {
        $_SESSION['testimonial_flash'] = 'Không thể gửi đánh giá, vui lòng thử lại.';
        $_SESSION['testimonial_flash_type'] = 'error';
    }

    header('Location: /cakev0/index.php#testimonial');
    exit;
}

$slides = [
    [
        'img_noi' => 'assets/uploads/banhkem/banh_69db2d7a89e889.17640738.jpg',
    ],
    [
        'img_noi' => 'assets/uploads/banhkem/banh_69db22e6a9f240.21037251.jpg',
    ],
    [
        'img_noi' => 'assets/uploads/banhkem/banh_69dcb5ebdc62b1.08342034.jpg',
    ],
    [
        'img_noi' => 'assets/uploads/banhngot/banh_69dbab0d23b419.90601663.jpg',
    ],
    [
        'img_noi' => 'assets/uploads/banhngot/banh_69dbaa45c851e9.30980417.jpg',
    ]
];

$bestLimit = 8;
$manualBestRes = $conn->query(
    "SELECT * FROM banh WHERE is_best_manual = 1 ORDER BY (best_rank = 0), best_rank ASC, id DESC LIMIT {$bestLimit}"
);
$manualBest = ($manualBestRes) ? $manualBestRes->fetch_all(MYSQLI_ASSOC) : [];

$topSellingRes = $conn->query(
    "SELECT b.*, SUM(oi.quantity) AS sold_qty
     FROM banh b
     JOIN order_items oi ON oi.banh_id = b.id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status IN ('paid','approved','delivered','completed')
     GROUP BY b.id
     ORDER BY sold_qty DESC, b.id DESC
     LIMIT {$bestLimit}"
);
$topSelling = ($topSellingRes) ? $topSellingRes->fetch_all(MYSQLI_ASSOC) : [];

$bestList = [];
$bestMap = [];
foreach ($manualBest as $item) {
    $bestMap[$item['id']] = true;
    $bestList[] = $item;
}
foreach ($topSelling as $item) {
    if (!isset($bestMap[$item['id']])) {
        $bestMap[$item['id']] = true;
        $bestList[] = $item;
    }
    if (count($bestList) >= $bestLimit) {
        break;
    }
}
if (count($bestList) < $bestLimit) {
    $fallbackLimit = $bestLimit - count($bestList);
    $fallbackRes = $conn->query(
        "SELECT * FROM banh ORDER BY id DESC LIMIT {$fallbackLimit}"
    );
    $fallback = ($fallbackRes) ? $fallbackRes->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($fallback as $item) {
        if (!isset($bestMap[$item['id']])) {
            $bestMap[$item['id']] = true;
            $bestList[] = $item;
        }
    }
}

$sql_review = "SELECT * FROM reviews WHERE status = 'approved' ORDER BY timestamp DESC LIMIT 3";
$res_review = $conn->query($sql_review);
$reviews    = ($res_review) ? $res_review->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
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
            flex-direction: column;
        }

        .hero-slider {
            width: min(520px, 100%);
            position: relative;
        }

        .hero-slide {
            width: 100%;
            height: auto;
            transform: scaleX(-1);
            opacity: 0;
            position: absolute;
            inset: 0;
            transition: opacity 0.35s ease;
        }

        .hero-slide.is-active {
            position: relative;
            opacity: 1;
        }

        .hero-dots {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 16px;
        }

        .hero-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1px solid #4a1d1f;
            background: transparent;
            cursor: pointer;
            padding: 0;
        }

        .hero-dot.is-active {
            background: #4a1d1f;
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
            height: 260px;
        }

        .strip-thumb {
            width: 230px;
            height: 180px;
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
            cursor: grab;
            scroll-behavior: smooth;
        }

        .best-list.is-dragging {
            cursor: grabbing;
            user-select: none;
        }

        .best-card {
            scroll-snap-align: start;
            width: 231px;
            flex: 0 0 auto;
        }

        .best-link {
            color: inherit;
            display: block;
        }

        .best-card img {
            width: 231px;
            height: 328px;
            border-radius: 33px;
            object-fit: cover;
            box-shadow: 4px 4px 16px rgba(0, 0, 0, 0.2);
            user-select: none;
            -webkit-user-drag: none;
        }

        .best-name {
            margin: 14px 0 8px;
            font-size: 22px;
            line-height: 33px;
            font-weight: 400;
            color: #000000;
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

        .testimonial-track {
            position: relative;
            min-height: 150px;
        }

        .testimonial-item {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.35s ease, transform 0.35s ease;
            position: absolute;
            inset: 0;
        }

        .testimonial-item.active {
            opacity: 1;
            transform: translateY(0);
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
            font-size: 18px;
            line-height: 30px;
            font-weight: 300;
            color: #272727;
            margin: 0;
        }

        .testimonial-meta {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: #4a1d1f;
        }

        .testimonial-name {
            font-size: 12px;
            font-weight: 300;
        }

        .testimonial-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #ffa903;
            font-size: 10px;
        }

        .testimonial-rating span {
            color: #707070;
            font-size: 14px;
        }

        .testimonial-message {
            margin: 22px auto 0;
            max-width: 520px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
            border: 1px solid #ead9bf;
        }

        .testimonial-message.success {
            color: #2f5f2f;
            border-color: #d6e8d6;
        }

        .testimonial-message.error {
            color: #a13a3a;
            border-color: #f1c7c7;
        }

        .testimonial-form {
            margin: 32px auto 0;
            max-width: 440px;
            background: #ffffff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 18px 36px rgba(74, 29, 31, 0.08);
            border: 1px solid #f0e1c9;
        }

        .testimonial-fields {
            display: grid;
            grid-template-columns: 1fr 160px;
            gap: 16px;
            margin-bottom: 16px;
        }

        .testimonial-input,
        .testimonial-select,
        .testimonial-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e4d4bd;
            border-radius: 10px;
            font-size: 10px;
            font-family: 'Poppins', sans-serif;
        }

        .testimonial-textarea {
            min-height: 120px;
            resize: vertical;
            grid-column: 1 / -1;
        }

        .testimonial-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 28px;
            background: #4a1d1f;
            color: #ffffff;
            border-radius: 30px;
            border: none;
            font-weight: 300;
            font-size: 12px;
            cursor: pointer;
        }

        .testimonial-dots {
            display: flex;
            justify-content: center;
            gap: 12px;

        }

        .testimonial-dots span {
            width: 10px;
            height: 10px;
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

        @media (max-width: 900px) {
            .hero {
                padding: 24px 0 80px;
            }

            .hero-inner {
                gap: 24px;
            }

            .hero-image {
                align-items: flex-start;
            }

            .hero-slider {
                width: min(420px, 100%);
            }

            .hero-strip {
                justify-content: center;
            }

            .strip-shell {
                flex-direction: column;
                height: auto;
                width: 100%;
                align-items: flex-start;
            }

            .strip-track {
                width: 100%;
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

            .testimonial-fields {
                grid-template-columns: 1fr;
            }

            .hero-actions {
                gap: 12px;
            }

            .btn-primary,
            .btn-outline {
                width: 100%;
                justify-content: center;
            }

            .strip-thumb {
                width: 200px;
                height: 150px;
            }
        }

        @media (max-width: 540px) {
            .hero-title {
                font-size: 28px;
                line-height: 40px;
            }

            .hero-desc {
                font-size: 16px;
                line-height: 26px;
            }

            .strip-thumb {
                width: 170px;
                height: 130px;
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

<?php
$heroImage = buildImageUrl($slides[0]['img_noi'] ?? 'assets/uploads/banhkem/banh_69da06e3dd40d8.60291082.jpg');
$heroStripImages = [
    'assets/uploads/banhngot/banh_69dbac321327a6.77842905.jpg',
    'assets/uploads/banhkem/banh_69db2d7a894377.87782327.jpg',  
    'assets/uploads/banhngot/banh_69dbabeec4c5e4.74925862.jpg',
    'assets/uploads/banhkem/banh_69dcb5ebdbde19.27662116.jpg',
    'assets/uploads/banhman/banh_69dc5bfa161e54.16618725.jpg',
    'assets/uploads/banhkem/banh_69dc5c1c6aae10.93189009.jpg'
];
?>

<?php include 'includes/header.php'; ?>

<div class="home-wrap">
    <section class="hero">
        <div class="hero-inner">
            <div class="hero-content">
                <h1 class="hero-title">Mang đến cho bạn niềm hạnh phúc<br>qua một miếng bánh</h1>
                <p class="hero-desc">Chúng tôi làm nhiều loại bánh khác nhau, bánh sô cô la, bánh quy mềm, bánh phô mai hoặc bất cứ thứ gì bạn muốn.</p>
                <div class="hero-actions">
                    <a class="btn-primary" href="/cakev0/pages/product.php">Đặt ngay</a>
                    <a class="btn-outline" href="/cakev0/pages/product.php">Xem tất cả</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-slider" id="heroSlider">
                    <?php foreach ($slides as $index => $slide): ?>
                        <img
                            class="hero-slide <?= $index === 0 ? 'is-active' : '' ?>"
                            src="<?= htmlspecialchars(buildImageUrl($slide['img_noi'])) ?>"
                            alt="Bánh">
                    <?php endforeach; ?>
                </div>
                <div class="hero-dots" id="heroDots">
                    <?php foreach ($slides as $index => $slide): ?>
                        <button
                            type="button"
                            class="hero-dot <?= $index === 0 ? 'is-active' : '' ?>"
                            data-index="<?= $index ?>"
                            aria-label="Slide <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
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
                    <?php $slug = !empty($p['slug']) ? $p['slug'] : slugify($p['ten_banh'], (int) $p['id']); ?>
                    <a href="/cakev0/product/<?= urlencode($slug) ?>" class="best-link">
                        <img src="<?= buildImageUrl($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_banh']) ?>">
                        <div class="best-name"><?= htmlspecialchars($p['ten_banh']) ?> </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;">
            <a class="section-btn" href="/cakev0/pages/product.php">Xem thêm <span></span></a>
        </div>
    </section>

    <section class="story-section">
        <div class="story-row">
            <div>
                <h3 class="story-title">Chúng tôi nướng bánh cho bạn thưởng thức.    Bánh mới ra lò mỗi ngày!</h3>
                <p class="story-desc">Chúng tôi sử dụng nguyên liệu chất lượng cao được lấy từ các đơn vị cung cấp uy tín. Các nhà đầu tư của chúng tôi đều là những người giàu kinh nghiệm trong lĩnh vực thực phẩm. Vì vậy, các sản phẩm chúng tôi sản xuất được đảm bảo về chất lượng và hương vị. Nó ngon đến mức bạn phải thử ngay!</p>
                <a class="story-link" href="/cakev0/pages/about.php">Đọc thêm <span></span></a>
            </div>
            <img class="story-image" src="/cakev0/assets/uploads/banhngot/banh_69dbab0d238210.70061934.jpg" alt="Fresh baked">
        </div>

        <div class="story-row reverse">
            <img class="story-image" src="/cakev0/assets/uploads/banhkem/banh_69db22e6a95e42.92082359.jpg" alt="Bakery space">
            <div>
                <h3 class="story-title">Hãy đến và chọn những món bạn yêu thích nhất!</h3>
                <p class="story-desc">Hãy đến trực tiếp cửa hàng của chúng tôi để thưởng thức hương vị thơm ngon của bánh vừa mới ra lò. Vừa thưởng thức bánh cùng một tách cà phê hoặc trà trong không gian cửa hàng tiện nghi của chúng tôi. Rất thích hợp để trò chuyện, gặp gỡ đồng nghiệp.</p>
                <a class="story-link" href="/cakev0/pages/contact.php">Đọc thêm <span></span></a>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="cta-inner">
            <h3 class="cta-title">Đối với các đơn đặt bánh Sự kiện lớn</h3>
            <p class="cta-desc">Vui lòng ghé thăm cửa hàng gần nhất của chúng tôi hoặc gọi điện cho chúng tôi theo số 0901 234 567 (08 giờ sáng đến 21 giờ tối tất cả các ngày trong tuần) để đặt hàng.</p>
            <a class="cta-btn" href="/cakev0/pages/contact.php">Liên hệ với chúng tôi ngay</a>
        </div>
    </section>

    <section class="testimonial" id="testimonial">
        <div class="testimonial-inner">
            <div class="testimonial-badge">Đánh giá</div>
            <div class="testimonial-track">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $index => $review):
                        $rating = normalizeStars($review['stars'] ?? '');
                    ?>
                        <div class="testimonial-item <?= $index === 0 ? 'active' : '' ?>">
                            <p class="testimonial-text">“<?= htmlspecialchars($review['text']) ?>”</p>
                            <div class="testimonial-meta">
                                <span class="testimonial-name"><?= htmlspecialchars($review['name']) ?></span>
                                <div class="testimonial-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= $rating ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <span><?= $rating ?>.0 Rating</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="testimonial-item active">
                        <p class="testimonial-text">“Bánh không trứng ở đây thực sự rất ngon. Tôi đã đặt một chiếc bánh Kit Kat và nó thực sự rất ngon. Chắc chắn đáng để thử.”</p>
                        <div class="testimonial-meta">
                            <span class="testimonial-name">Khách hàng</span>
                            <div class="testimonial-rating">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-regular fa-star"></i>
                                <span>4.0 Rating</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="testimonial-dots">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $index => $review): ?>
                        <span class="<?= $index === 0 ? 'active' : '' ?>"></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="active"></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($_SESSION['testimonial_flash'])):
                $flashType = $_SESSION['testimonial_flash_type'] ?? 'success';
            ?>
                <div class="testimonial-message <?= htmlspecialchars($flashType) ?>">
                    <?= htmlspecialchars($_SESSION['testimonial_flash']) ?>
                </div>
                <?php unset($_SESSION['testimonial_flash'], $_SESSION['testimonial_flash_type']); ?>
            <?php endif; ?>

            <form class="testimonial-form" method="POST" action="/cakev0/index.php#testimonial">
                <input type="hidden" name="submit_testimonial" value="1">
                <div class="testimonial-fields">
                    <input class="testimonial-input" type="text" name="review_name" placeholder="Tên của bạn" required>
                    <select class="testimonial-select" name="review_rating" required>
                        <option value="">Rating</option>
                        <option value="5">5 sao</option>
                        <option value="4">4 sao</option>
                        <option value="3">3 sao</option>
                        <option value="2">2 sao</option>
                        <option value="1">1 sao</option>
                    </select>
                    <textarea class="testimonial-textarea" name="review_text" placeholder="Chia sẻ cảm nhận của bạn" required></textarea>
                </div>
                <button class="testimonial-submit" type="submit">Gửi đánh giá</button>
            </form>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const heroSlider = document.getElementById('heroSlider');
        const heroSlides = heroSlider ? Array.from(heroSlider.querySelectorAll('.hero-slide')) : [];
        const heroDots = Array.from(document.querySelectorAll('.hero-dot'));
        if (heroSlides.length > 1) {
            let heroIndex = 0;
            const showHero = (index) => {
                const total = heroSlides.length;
                const next = (index + total) % total;
                heroSlides.forEach((slide, i) => slide.classList.toggle('is-active', i === next));
                heroDots.forEach((dot, i) => dot.classList.toggle('is-active', i === next));
                heroIndex = next;
            };

            heroDots.forEach((dot) => {
                dot.addEventListener('click', () => {
                    const index = parseInt(dot.dataset.index || '0', 10);
                    showHero(index);
                });
            });

            setInterval(() => showHero(heroIndex + 1), 5000);
        }

        const bestList = document.querySelector('.best-list');
        if (bestList) {
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            const endDrag = () => {
                isDown = false;
                bestList.classList.remove('is-dragging');
            };

            bestList.addEventListener('mousedown', (e) => {
                isDown = true;
                bestList.classList.add('is-dragging');
                startX = e.pageX - bestList.offsetLeft;
                scrollLeft = bestList.scrollLeft;
            });

            bestList.addEventListener('mouseleave', endDrag);
            bestList.addEventListener('mouseup', endDrag);

            bestList.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - bestList.offsetLeft;
                const walk = x - startX;
                bestList.scrollLeft = scrollLeft - walk;
            });

            bestList.querySelectorAll('img').forEach((img) => {
                img.setAttribute('draggable', 'false');
            });
            bestList.addEventListener('dragstart', (e) => e.preventDefault());
        }

        const items = Array.from(document.querySelectorAll('.testimonial-item'));
        const dots = Array.from(document.querySelectorAll('.testimonial-dots span'));
        if (items.length <= 1) return;

        let activeIndex = 0;
        const activate = (index) => {
            items.forEach((item, i) => item.classList.toggle('active', i === index));
            dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
            activeIndex = index;
        };

        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => activate(i));
        });

        setInterval(() => {
            const nextIndex = (activeIndex + 1) % items.length;
            activate(nextIndex);
        }, 6000);
    });
</script>

<button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

<?php include 'includes/footer.html'; ?>

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
<?php if(isset($conn)) $conn->close(); ?>