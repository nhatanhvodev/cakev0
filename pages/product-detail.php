<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config/connect.php';

$pageTitle = 'Chi tiết sản phẩm';
$slug = trim($_GET['slug'] ?? '');
$today = date('Y-m-d');
$isLoggedIn = isset($_SESSION['user_id']);
$isFavorite = false;
$favoritesTableReady = false;

function imgPath($path) {
    $fallback = '/cakev0/assets/img/no-image.jpg';
    if (!$path) return $fallback;

    $path = trim((string) $path);
    if ($path === '') return $fallback;

    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:image/')) {
        return $path;
    }

    // Keep only project-relative path when SQL stores absolute machine path.
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

function normalizeKeyword(string $value): string {
    $text = safeTransliterate($value);
    $text = strtolower($text ?: $value);
    $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function getFlavorHints(string $name): array {
    $normalized = normalizeKeyword($name);
    $hints = [];
    $map = [
        'dau' => 'dau',
        'choco' => 'choco',
        'socola' => 'socola',
        'soco' => 'soco',
        'muffin' => 'muffin',
        'cupcake' => 'cupcake'
    ];

    foreach ($map as $needle => $value) {
        if (strpos($normalized, $needle) !== false) {
            $hints[] = $value;
        }
    }

    return array_values(array_unique($hints));
}

if ($slug === '') {
    http_response_code(404);
    echo 'Không tìm thấy sản phẩm.';
    exit;
}

$selected = null;
$slugParam = $slug;

$stmt = $conn->prepare(
    "SELECT b.*, p.gia_khuyen_mai
     FROM banh b
     LEFT JOIN promotions p ON b.id = p.banh_id
     AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
     WHERE b.slug = ?
     LIMIT 1"
);
$stmt->bind_param('sss', $today, $today, $slugParam);
$stmt->execute();
$selected = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$selected && preg_match('/-(\d+)$/', $slugParam, $matches)) {
    $id = (int) $matches[1];
    $stmt = $conn->prepare(
        "SELECT b.*, p.gia_khuyen_mai
         FROM banh b
         LEFT JOIN promotions p ON b.id = p.banh_id
         AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
         WHERE b.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('ssi', $today, $today, $id);
    $stmt->execute();
    $selected = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$selected) {
    http_response_code(404);
    echo 'Không tìm thấy sản phẩm.';
    exit;
}

if ($conn) {
    $favoriteTableResult = $conn->query("SHOW TABLES LIKE 'favorites'");
    if ($favoriteTableResult) {
        $favoritesTableReady = $favoriteTableResult->num_rows > 0;
    }
}

if ($isLoggedIn && $favoritesTableReady) {
    $uid = (int) $_SESSION['user_id'];
    $productId = (int) $selected['id'];
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND banh_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $uid, $productId);
        $stmt->execute();
        $isFavorite = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$currentSlug = $selected['slug'] ?? '';
if ($currentSlug === '') {
    $currentSlug = slugify($selected['ten_banh'], (int) $selected['id']);
    $stmt = $conn->prepare("UPDATE banh SET slug = ? WHERE id = ?");
    $stmt->bind_param('si', $currentSlug, $selected['id']);
    $stmt->execute();
    $stmt->close();
}

if ($currentSlug !== $slugParam) {
    header('Location: /cakev0/product/' . urlencode($currentSlug), true, 301);
    exit;
}

$related = [];
$flavors = getFlavorHints($selected['ten_banh']);
$relatedIds = [];

if (!empty($flavors)) {
    $placeholders = implode(' OR ', array_fill(0, count($flavors), 'b.ten_banh LIKE ?'));
    $sql = "SELECT b.*, p.gia_khuyen_mai
            FROM banh b
            LEFT JOIN promotions p ON b.id = p.banh_id
            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
            WHERE b.id <> ? AND b.loai = ? AND (" . $placeholders . ")
            LIMIT 6";
    $stmt = $conn->prepare($sql);
    $types = 'ssis' . str_repeat('s', count($flavors));
    $params = [$today, $today, (int) $selected['id'], $selected['loai']];
    foreach ($flavors as $flavor) {
        $params[] = '%' . $flavor . '%';
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($related as $item) {
        $relatedIds[$item['id']] = true;
    }
}

if (count($related) < 6) {
    $limit = 6 - count($related);
    $sql = "SELECT b.*, p.gia_khuyen_mai
            FROM banh b
            LEFT JOIN promotions p ON b.id = p.banh_id
            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
            WHERE b.id <> ? AND b.loai = ?
            ORDER BY b.id DESC
            LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssis', $today, $today, $selected['id'], $selected['loai']);
    $stmt->execute();
    $fallback = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($fallback as $item) {
        if (!isset($relatedIds[$item['id']])) {
            $related[] = $item;
            $relatedIds[$item['id']] = true;
        }
    }
}

$reviews = [];
$reviewCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$stmt = $conn->prepare(
    "SELECT rating, COUNT(*) AS total
     FROM product_reviews
     WHERE product_id = ?
     GROUP BY rating"
);
$stmt->bind_param('i', $selected['id']);
$stmt->execute();
$countRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($countRows as $row) {
    $reviewCounts[(int) $row['rating']] = (int) $row['total'];
}

$galleryImages = [];
$stmt = $conn->prepare(
    "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id ASC"
);
$stmt->bind_param('i', $selected['id']);
$stmt->execute();
$galleryImages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mainImage = $selected['hinh_anh'] ?? '';
$finalGallery = [];
if ($mainImage !== '') {
    $finalGallery[] = ['image_path' => $mainImage];
}
foreach ($galleryImages as $img) {
    if (!isset($img['image_path'])) {
        continue;
    }
    if ($mainImage !== '' && $img['image_path'] === $mainImage) {
        continue;
    }
    $finalGallery[] = $img;
}
if (empty($finalGallery)) {
    $finalGallery = [['image_path' => $mainImage]];
}
$galleryImages = $finalGallery;

$stmt = $conn->prepare(
    "SELECT name, rating, content, created_at
     FROM product_reviews
     WHERE product_id = ?
     ORDER BY created_at DESC
     LIMIT 6"
);
$stmt->bind_param('i', $selected['id']);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$reviewTotal = array_sum($reviewCounts);
$weightedRating = 0;
foreach ($reviewCounts as $ratingValue => $ratingCount) {
    $weightedRating += $ratingValue * $ratingCount;
}
$averageRating = $reviewTotal > 0 ? $weightedRating / $reviewTotal : 0;
$tracksStock = array_key_exists('stock', $selected);
$stock = $tracksStock ? max(0, (int) $selected['stock']) : null;
$inStock = !$tracksStock || $stock > 0;
$hasSale = !empty($selected['gia_khuyen_mai']);
$finalPrice = (float) ($hasSale ? $selected['gia_khuyen_mai'] : $selected['gia']);
$discount = ($hasSale && (float) $selected['gia'] > 0)
    ? (int) round(100 - (($selected['gia_khuyen_mai'] / $selected['gia']) * 100))
    : 0;
$categoryLabels = [
    'ngot' => 'Bánh ngọt',
    'man' => 'Bánh mặn',
    'mi' => 'Bánh mì',
    'kem' => 'Bánh kem'
];
$categoryLabel = $categoryLabels[$selected['loai']] ?? 'Sản phẩm';
$productCode = '#' . (int) $selected['id'];

$safeDescriptionHtml = '';
$descriptionExcerpt = 'Thông tin chi tiết sẽ được cập nhật sớm.';
if (!empty($selected['mo_ta'])) {
    $decodedDescription = html_entity_decode($selected['mo_ta'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeDescriptionHtml = strip_tags(
        $decodedDescription,
        '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6>'
    );
    $safeDescriptionHtml = (string) preg_replace_callback(
        '/<(\/?)h[1-6]\b[^>]*>/i',
        static fn(array $match): string => $match[1] === '/' ? '</h3>' : '<h3>',
        $safeDescriptionHtml
    );
    $safeDescriptionHtml = (string) preg_replace(
        '/<(p|br|strong|b|em|i|u|ul|ol|li|h3)\b[^>]*>/i',
        '<$1>',
        $safeDescriptionHtml
    );
    $plainDescription = trim((string) preg_replace('/\s+/u', ' ', strip_tags($decodedDescription)));
    if ($plainDescription !== '') {
        $descriptionExcerpt = function_exists('mb_substr')
            ? mb_substr($plainDescription, 0, 190, 'UTF-8')
            : substr($plainDescription, 0, 190);
        $descriptionLength = function_exists('mb_strlen')
            ? mb_strlen($plainDescription, 'UTF-8')
            : strlen($plainDescription);
        if ($descriptionLength > 190) {
            $descriptionExcerpt .= '…';
        }
    }
}

$extraLinks = '<link rel="stylesheet" href="/cakev0/assets/css/style.css">';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($selected['ten_banh']) ?> | Gấu Bakery</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

<style>
body {
    background: #ffffff;
    color: #272727;
    font-family: 'Poppins', sans-serif;
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.page-content {
    flex: 1;
}

.detail-wrap {
    max-width: 1180px;
    margin: 24px auto 40px;
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.detail-hero {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 28px;
    align-items: start;
    background: #fff;
    border-radius: 28px;
    border: 1px solid #f3e0be;
    box-shadow: 0 18px 36px rgba(74, 29, 31, 0.08);
    padding: 24px;
}

.detail-gallery-shell {
    position: relative;
}

.detail-gallery {
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    border-radius: 18px;
    cursor: grab;
    scrollbar-width: none;
}

.detail-gallery::-webkit-scrollbar {
    display: none;
}

.detail-gallery.is-dragging {
    cursor: grabbing;
    user-select: none;
}

.detail-track {
    display: flex;
    width: 100%;
}

.detail-track img {
    width: 100%;
    height: 500px;
    flex: 0 0 100%;
    border-radius: 18px;
    object-fit: contain;
    background: #f7f2ee;
    scroll-snap-align: start;
    user-select: none;
    -webkit-user-drag: none;
}

.detail-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding-top: 10px;
}

.detail-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #d9d3d0;
    border: none;
    padding: 0;
    cursor: pointer;
}

.detail-dot.active {
    background: #4a1d1f;
}

.detail-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    color: #4a1d1f;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 8px 16px rgba(74, 29, 31, 0.18);
}

.detail-nav-btn.prev {
    left: 10px;
}

.detail-nav-btn.next {
    right: 10px;
}

.detail-nav-btn.is-hidden {
    opacity: 0;
    pointer-events: none;
}

.detail-info h1 {
    margin: 0 0 10px;
    font-size: 32px;
    color: #4a1d1f;
}

.detail-price {
    font-size: 22px;
    font-weight: 700;
    color: #4a1d1f;
    margin-bottom: 12px;
}

.detail-price del {
    color: #b7a39a;
    font-weight: 500;
    margin-right: 8px;
}

.detail-discount {
    margin-left: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #b42318;
}


.detail-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 12px;
}

.qty-input {
    width: 80px;
    padding: 8px 10px;
    border: 1px solid #ead9bf;
    border-radius: 10px;
    text-align: center;
    font-weight: 600;
}

.cta-btn {
    padding: 12px 24px;
    border-radius: 12px;
    background: #4a1d1f;
    color: #fbedcd;
    font-weight: 600;
    border: none;
    cursor: pointer;
}

.wishlist-btn {
    padding: 12px 20px;
    border-radius: 14px;
    border: 1.5px solid #f3e0be;
    background: #fff9f1;
    color: #4a1d1f;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: background 160ms cubic-bezier(0.23, 1, 0.32, 1), border-color 160ms cubic-bezier(0.23, 1, 0.32, 1), color 160ms cubic-bezier(0.23, 1, 0.32, 1), transform 160ms cubic-bezier(0.23, 1, 0.32, 1), box-shadow 160ms cubic-bezier(0.23, 1, 0.32, 1);
    box-shadow: 0 4px 12px rgba(74, 29, 31, 0.05);
}

.wishlist-btn:hover {
    background: #fbedcd;
    border-color: #4a1d1f;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(74, 29, 31, 0.12);
}

.wishlist-btn.is-active {
    background: #ffefef;
    border-color: #f3b8b8;
    color: #b42318;
}

.section-card {
    background: #fff;
    border-radius: 24px;
    border: 1px solid #f3e0be;
    box-shadow: 0 16px 32px rgba(74, 29, 31, 0.06);
    padding: 20px 22px;
}

.section-title {
    margin: 0 0 16px;
    color: #4a1d1f;
    font-size: 20px;
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.related-card {
    border: 1px solid #f3e0be;
    border-radius: 18px;
    padding: 12px;
    background: #fff;
}

.related-card img {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    border-radius: 14px;
}

.related-name {
    font-weight: 600;
    margin-top: 8px;
    font-size: 14px;
}

.review-item {
    border-bottom: 1px solid #f1e4d0;
    padding: 14px 0;
}

.review-item:last-child {
    border-bottom: none;
}

.review-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.review-name {
    font-weight: 600;
    color: #4a1d1f;
}

.review-stars {
    color: #f9a602;
    font-size: 14px;
}

.review-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}

.review-filter-btn {
    padding: 6px 14px;
    border-radius: 999px;
    border: 1px solid #e9d9c2;
    background: #fff;
    color: #4a1d1f;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.review-filter-btn.active {
    background: #4a1d1f;
    color: #fbedcd;
    border-color: #4a1d1f;
}

@media (max-width: 980px) {
    .detail-hero {
        grid-template-columns: 1fr;
    }

    .related-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .detail-wrap {
        padding: 0 16px;
        margin: 16px auto 32px;
    }

    .detail-hero {
        padding: 18px;
    }

    .detail-track img {
        height: 280px;
    }

    .detail-info h1 {
        font-size: 26px;
    }

    .detail-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 16px;
    }

    .qty-input {
        grid-column: span 2;
        width: 100% !important;
        height: 48px;
    }

    .cta-btn, .wishlist-btn {
        height: 50px;
        font-size: 14px;
        padding: 0 10px;
        width: 100%;
    }

    .related-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .related-card {
        padding: 12px;
        border-radius: 20px;
    }

    .related-card img {
        aspect-ratio: 1 / 1;
        border-radius: 14px;
    }

    .detail-wrap {
        padding: 0 16px;
        margin: 16px auto 60px; /* Increased bottom margin */
        gap: 24px;
    }
}

/* ===== Gấu Bakery — Product detail "Warm Artisan" ===== */
:root {
    --pd-bg: #fbf6ee;
    --pd-surface: #ffffff;
    --pd-ink: #3a2117;
    --pd-muted: #76645a;
    --pd-maroon: #4a1d1f;
    --pd-maroon-deep: #2f1415;
    --pd-caramel: #e07b39;
    --pd-caramel-soft: #fff2e8;
    --pd-honey: #f3e0be;
    --pd-success: #25663f;
    --pd-danger: #a52c2c;
    --pd-gold: #e8a93c;
    --pd-shadow: 0 16px 36px rgba(74, 29, 31, 0.08);
    --pd-shadow-strong: 0 24px 52px rgba(74, 29, 31, 0.14);
}

body {
    background: var(--pd-bg);
    color: var(--pd-ink);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    overflow-x: hidden;
}

.product-detail :where(h1, h2, h3) { text-wrap: balance; }
.product-detail :where(p, li) { text-wrap: pretty; }

.detail-wrap {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 20px 72px;
    box-sizing: border-box;
    gap: 28px;
}

.product-breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    color: var(--pd-muted);
    font-size: 13px;
}

.product-breadcrumb a {
    color: var(--pd-maroon);
    font-weight: 600;
    text-decoration: none;
}

.product-breadcrumb a:hover { color: var(--pd-caramel); }

.detail-hero {
    grid-template-columns: minmax(0, 1.06fr) minmax(360px, 0.94fr);
    gap: 40px;
    align-items: start;
    padding: 28px;
    border: 1px solid rgba(74, 29, 31, 0.08);
    border-radius: 24px;
    background: var(--pd-surface);
    box-shadow: var(--pd-shadow);
}

.detail-gallery-shell {
    overflow: hidden;
    border-radius: 18px;
    background: var(--pd-caramel-soft);
    box-shadow: inset 0 0 0 1px rgba(74, 29, 31, 0.06);
}

.detail-gallery { border-radius: 18px; }

.detail-track img {
    height: auto;
    aspect-ratio: 1 / 1;
    border-radius: 18px;
    background: var(--pd-caramel-soft);
    object-fit: cover;
    outline: 1px solid rgba(74, 29, 31, 0.06);
    outline-offset: -1px;
}

.detail-dots {
    justify-content: flex-start;
    gap: 10px;
    padding: 12px 2px 0;
    overflow-x: auto;
    scrollbar-width: thin;
}

.detail-dot {
    width: 68px;
    height: 68px;
    flex: 0 0 68px;
    padding: 3px;
    border: 2px solid transparent;
    border-radius: 12px;
    background: var(--pd-surface);
    box-shadow: inset 0 0 0 1px var(--pd-honey);
}

.detail-dot img {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: 8px;
    object-fit: cover;
}

.detail-dot.active {
    border-color: var(--pd-caramel);
    background: var(--pd-surface);
    box-shadow: none;
}

.detail-nav-btn {
    width: 44px;
    height: 44px;
    color: var(--pd-maroon);
    box-shadow: 0 8px 18px rgba(74, 29, 31, 0.16);
    transition: opacity 140ms cubic-bezier(0.23, 1, 0.32, 1), transform 140ms cubic-bezier(0.23, 1, 0.32, 1), background 140ms cubic-bezier(0.23, 1, 0.32, 1);
}

.detail-info {
    position: sticky;
    top: 150px;
}

.detail-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--pd-caramel);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.detail-info h1 {
    margin-bottom: 12px;
    color: var(--pd-maroon);
    font-size: clamp(30px, 4vw, 42px);
    line-height: 1.15;
    letter-spacing: -0.035em;
}

.detail-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 14px;
    margin-bottom: 18px;
    color: var(--pd-muted);
    font-size: 13px;
}

.detail-meta a {
    color: var(--pd-maroon);
    font-weight: 600;
    text-decoration: none;
}

.detail-rating-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.detail-rating-link i { color: var(--pd-gold); }

.detail-price {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
    color: var(--pd-maroon);
    font-size: clamp(24px, 3vw, 30px);
    font-variant-numeric: tabular-nums;
}

.detail-price del {
    margin: 0;
    color: #9a877d;
    font-size: 15px;
}

.detail-discount {
    margin: 0;
    padding: 4px 8px;
    border-radius: 999px;
    background: #fff0ee;
    color: var(--pd-danger);
    font-size: 12px;
}

.availability {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 32px;
    margin-bottom: 16px;
    padding: 4px 11px;
    border-radius: 999px;
    background: #edf8f1;
    color: var(--pd-success);
    font-size: 13px;
    font-weight: 700;
}

.availability::before {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #2f8a54;
    content: "";
}

.availability.is-low {
    background: var(--pd-caramel-soft);
    color: #964819;
}

.availability.is-low::before { background: var(--pd-caramel); }

.availability.is-out {
    background: #fff0ee;
    color: var(--pd-danger);
}

.availability.is-out::before { background: var(--pd-danger); }

.detail-summary {
    max-width: 60ch;
    margin: 0 0 20px;
    color: var(--pd-muted);
    font-size: 14px;
    line-height: 1.75;
}

.detail-actions {
    display: grid;
    grid-template-columns: 132px minmax(0, 1fr);
    align-items: stretch;
    gap: 12px;
    margin-top: 0;
}

.quantity-field { display: grid; gap: 7px; }

.quantity-field > label {
    color: var(--pd-muted);
    font-size: 12px;
    font-weight: 600;
}

.quantity-control {
    min-height: 50px;
    display: grid;
    grid-template-columns: 44px minmax(36px, 1fr) 44px;
    overflow: hidden;
    border: 1px solid var(--pd-honey);
    border-radius: 12px;
    background: var(--pd-surface);
}

.qty-step {
    width: 44px;
    min-height: 48px;
    border: 0;
    background: transparent;
    color: var(--pd-maroon);
    font-size: 18px;
    cursor: pointer;
}

.qty-input {
    width: 100%;
    min-width: 0;
    height: 48px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: var(--pd-ink);
    font: 700 15px/1 'Poppins', sans-serif;
    appearance: textfield;
}

.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button { margin: 0; appearance: none; }

.cta-wrap { display: grid; gap: 7px; }

.cta-spacer {
    color: transparent;
    font-size: 12px;
    user-select: none;
}

.cta-btn,
.wishlist-btn {
    min-height: 50px;
    padding: 0 18px;
    border-radius: 12px;
    font: 600 14px/1.2 'Poppins', sans-serif;
}

.cta-btn {
    background: var(--pd-maroon);
    color: #fff8ed;
    box-shadow: 0 10px 22px rgba(74, 29, 31, 0.2);
    transition: background 140ms cubic-bezier(0.23, 1, 0.32, 1), transform 140ms cubic-bezier(0.23, 1, 0.32, 1), box-shadow 140ms cubic-bezier(0.23, 1, 0.32, 1);
}

.cta-btn:disabled {
    background: #c5b7ad;
    color: #fffaf5;
    cursor: not-allowed;
    box-shadow: none;
}

.wishlist-btn {
    grid-column: 1 / -1;
    width: 100%;
    border-color: var(--pd-honey);
    background: #fffaf3;
    box-shadow: none;
}

.promise-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

.promise-card {
    min-height: 84px;
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    align-content: center;
    gap: 2px 8px;
    padding: 11px;
    border-radius: 14px;
    background: #fff8ed;
    color: var(--pd-ink);
    text-decoration: none;
    box-shadow: inset 0 0 0 1px var(--pd-honey);
}

.promise-card i {
    grid-row: 1 / span 2;
    align-self: center;
    color: var(--pd-caramel);
    font-size: 20px;
}

.promise-card strong {
    color: var(--pd-maroon);
    font-size: 12px;
}

.promise-card span {
    color: var(--pd-muted);
    font-size: 11px;
    line-height: 1.4;
}

.section-card {
    padding: 28px;
    border: 1px solid rgba(74, 29, 31, 0.08);
    border-radius: 22px;
    box-shadow: var(--pd-shadow);
}

.section-heading-row {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.section-eyebrow {
    display: block;
    margin-bottom: 5px;
    color: var(--pd-caramel);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.section-title {
    margin: 0;
    color: var(--pd-maroon);
    font-size: clamp(21px, 3vw, 28px);
    line-height: 1.25;
}

.description-content {
    max-width: 72ch;
    color: var(--pd-muted);
    font-size: 15px;
    line-height: 1.8;
}

.description-content > :first-child { margin-top: 0; }
.description-content > :last-child { margin-bottom: 0; }

.review-overview {
    display: grid;
    grid-template-columns: 160px minmax(0, 1fr);
    gap: 28px;
    align-items: center;
    padding: 18px;
    border-radius: 18px;
    background: #fff8ed;
    box-shadow: inset 0 0 0 1px var(--pd-honey);
}

.review-score {
    text-align: center;
    color: var(--pd-maroon);
}

.review-score strong {
    display: block;
    font-size: 40px;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.review-score span {
    display: block;
    margin-top: 8px;
    color: var(--pd-muted);
    font-size: 12px;
}

.review-bars { display: grid; gap: 6px; }

.review-bar {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 28px;
    align-items: center;
    gap: 8px;
    color: var(--pd-muted);
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}

.review-bar-track {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: #eadfce;
}

.review-bar-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--pd-gold);
}

.review-filters {
    margin: 18px 0 4px;
}

.review-filter-btn {
    min-height: 44px;
    padding: 8px 15px;
    border-color: var(--pd-honey);
    font-family: inherit;
}

.review-filter-btn.active {
    background: var(--pd-maroon);
    color: #fff8ed;
}

.review-item {
    padding: 18px 0;
    border-color: #f0e3d0;
}

.review-copy {
    margin: 8px 0 0;
    color: var(--pd-muted);
    line-height: 1.7;
}

.review-date {
    display: block;
    margin-top: 6px;
    color: #938078;
    font-size: 12px;
}

.review-empty-filter {
    margin: 18px 0 0;
    color: var(--pd-muted);
}

.related-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }

.related-card {
    color: var(--pd-ink);
    text-decoration: none;
    box-shadow: 0 8px 20px rgba(74, 29, 31, 0.06);
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), box-shadow 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.related-card img {
    aspect-ratio: 1 / 1;
    outline: 1px solid rgba(74, 29, 31, 0.06);
    outline-offset: -1px;
}

.related-name {
    min-height: 42px;
    color: var(--pd-maroon);
    line-height: 1.45;
}

.related-price {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
    color: var(--pd-maroon);
    font-size: 14px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.related-price del {
    color: #9a877d;
    font-size: 11px;
    font-weight: 500;
}

.mobile-buy-bar { display: none; }

.product-detail :focus-visible,
.mobile-buy-bar :focus-visible {
    outline: 3px solid rgba(224, 123, 57, 0.48);
    outline-offset: 3px;
}

@media (hover: hover) and (pointer: fine) {
    .cta-btn:hover { background: var(--pd-maroon-deep); box-shadow: var(--pd-shadow-strong); }
    .detail-nav-btn:hover { background: #fff; transform: translateY(-50%) scale(1.04); }
    .promise-card:hover { box-shadow: inset 0 0 0 1px var(--pd-caramel); }
    .related-card:hover { transform: translateY(-3px); box-shadow: 0 16px 30px rgba(74, 29, 31, 0.12); }
}

@media (max-width: 980px) {
    .detail-hero { grid-template-columns: minmax(0, 1fr); gap: 28px; }
    .detail-info { position: static; }
    .related-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .detail-wrap { padding: 18px 14px 92px; }
    .detail-hero { padding: 16px; border-radius: 20px; }
    .detail-info h1 { font-size: 28px; }
    .detail-actions { grid-template-columns: 124px minmax(0, 1fr); }
    .promise-grid { grid-template-columns: 1fr; }
    .promise-card { min-height: 68px; }
    .section-card { padding: 20px 16px; border-radius: 18px; }
    .review-overview { grid-template-columns: 1fr; gap: 18px; }
    .review-score { display: flex; align-items: baseline; justify-content: center; gap: 8px; }
    .review-score span { margin: 0; }
    .related-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }

    .mobile-buy-bar {
        position: fixed;
        right: 88px;
        bottom: 12px;
        left: 12px;
        z-index: 1450;
        min-height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 10px 8px 14px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 16px 38px rgba(47, 20, 21, 0.24);
        opacity: 0;
        visibility: hidden;
        transform: translateY(18px);
        transition: opacity 180ms cubic-bezier(0.23, 1, 0.32, 1), transform 180ms cubic-bezier(0.23, 1, 0.32, 1), visibility 180ms cubic-bezier(0.23, 1, 0.32, 1);
    }

    .mobile-buy-bar.is-visible {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .mobile-buy-price {
        min-width: 0;
        color: var(--pd-maroon);
        font-size: 13px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .mobile-buy-btn {
        min-width: 104px;
        min-height: 46px;
        padding: 0 14px;
        border: 0;
        border-radius: 12px;
        background: var(--pd-maroon);
        color: #fff8ed;
        font: 600 13px/1 'Poppins', sans-serif;
        cursor: pointer;
    }

    .mobile-buy-btn:disabled { background: #c5b7ad; cursor: not-allowed; }
}

@media (max-width: 440px) {
    .detail-actions { grid-template-columns: 1fr; }
    .wishlist-btn { grid-column: auto; }
    .cta-spacer { display: none; }
    .detail-dot { width: 60px; height: 60px; flex-basis: 60px; }
    .related-name { font-size: 13px; }
    .mobile-buy-price { font-size: 12px; }
    .mobile-buy-btn { min-width: 96px; }
}

@media (prefers-reduced-motion: reduce) {
    .product-detail *,
    .product-detail *::before,
    .product-detail *::after,
    .mobile-buy-bar {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>

<main class="page-content product-detail" id="main-content">
<div class="detail-wrap">
    <nav class="product-breadcrumb" aria-label="Đường dẫn trang">
        <a href="/cakev0/">Trang chủ</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <a href="/cakev0/pages/product.php?loai=<?= urlencode($selected['loai']) ?>"><?= htmlspecialchars($categoryLabel) ?></a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page"><?= htmlspecialchars($selected['ten_banh']) ?></span>
    </nav>

    <section class="detail-hero" aria-labelledby="productTitle">
        <div class="detail-media">
            <div class="detail-gallery-shell">
                <div class="detail-gallery" role="region" aria-label="Hình ảnh <?= htmlspecialchars($selected['ten_banh']) ?>" tabindex="0">
                    <div class="detail-track">
                        <?php foreach ($galleryImages as $index => $img): ?>
                            <img src="<?= imgPath($img['image_path']) ?>"
                                 alt="<?= htmlspecialchars($selected['ten_banh']) ?> — ảnh <?= $index + 1 ?> / <?= count($galleryImages) ?>"
                                 width="720"
                                 height="720"
                                 decoding="async"
                                 <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (count($galleryImages) > 1): ?>
                    <button class="detail-nav-btn prev" type="button" aria-label="Xem ảnh trước">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>
                    <button class="detail-nav-btn next" type="button" aria-label="Xem ảnh tiếp theo">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php if (count($galleryImages) > 1): ?>
                <div class="detail-dots" aria-label="Chọn ảnh sản phẩm">
                    <?php foreach ($galleryImages as $index => $img): ?>
                        <button class="detail-dot <?= $index === 0 ? 'active' : '' ?>"
                                type="button"
                                data-index="<?= $index ?>"
                                aria-label="Xem ảnh <?= $index + 1 ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>">
                            <img src="<?= imgPath($img['image_path']) ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-info">
            <span class="detail-kicker"><i class="fa-solid fa-cookie-bite" aria-hidden="true"></i> <?= htmlspecialchars($categoryLabel) ?></span>
            <h1 id="productTitle"><?= htmlspecialchars($selected['ten_banh']) ?></h1>
            <div class="detail-meta">
                <span>Mã <?= htmlspecialchars($productCode) ?></span>
                <span aria-hidden="true">•</span>
                <?php if ($reviewTotal > 0): ?>
                    <a class="detail-rating-link" href="#reviews">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <?= number_format($averageRating, 1) ?> (<?= $reviewTotal ?> đánh giá)
                    </a>
                <?php else: ?>
                    <a href="#reviews">Chưa có đánh giá</a>
                <?php endif; ?>
            </div>

            <div class="detail-price" aria-label="Giá sản phẩm">
                <?php if ($hasSale): ?>
                    <span><?= number_format($finalPrice) ?> VNĐ</span>
                    <del><?= number_format($selected['gia']) ?> VNĐ</del>
                    <?php if ($discount > 0): ?><span class="detail-discount">Tiết kiệm <?= $discount ?>%</span><?php endif; ?>
                <?php else: ?>
                    <span><?= number_format($finalPrice) ?> VNĐ</span>
                <?php endif; ?>
            </div>

            <?php if ($tracksStock && !$inStock): ?>
                <span class="availability is-out">Tạm hết hàng</span>
            <?php elseif ($tracksStock && $stock <= 5): ?>
                <span class="availability is-low">Chỉ còn <?= $stock ?> sản phẩm</span>
            <?php endif; ?>

            <p class="detail-summary"><?= htmlspecialchars($descriptionExcerpt) ?></p>

            <div class="detail-actions">
                <div class="quantity-field">
                    <label for="detailQty">Số lượng</label>
                    <span class="quantity-control">
                        <button class="qty-step" type="button" data-qty-step="-1" aria-label="Giảm số lượng" <?= $inStock ? '' : 'disabled' ?>>−</button>
                        <input class="qty-input" type="number" id="detailQty" value="1" min="1" <?= !$inStock ? 'disabled' : ($tracksStock ? 'max="' . $stock . '"' : '') ?> inputmode="numeric">
                        <button class="qty-step" type="button" data-qty-step="1" aria-label="Tăng số lượng" <?= $inStock ? '' : 'disabled' ?>>+</button>
                    </span>
                </div>
                <div class="cta-wrap">
                    <span class="cta-spacer" aria-hidden="true">Hành động</span>
                    <button class="cta-btn"
                            type="button"
                            id="mainAddToCart"
                            <?= $inStock ? '' : 'disabled aria-disabled="true"' ?>
                            onclick="addDetailToCart(<?= (int) $selected['id'] ?>, this)">
                        <i class="fa-solid <?= $inStock ? 'fa-cart-plus' : 'fa-clock' ?>" aria-hidden="true"></i>
                        <?= $inStock ? 'Thêm vào giỏ hàng' : 'Tạm hết hàng' ?>
                    </button>
                </div>
                <button type="button"
                        class="wishlist-btn <?= $isFavorite ? 'is-active' : '' ?>"
                        id="detailFavoriteBtn"
                        data-product-id="<?= (int) $selected['id'] ?>"
                        aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
                        onclick="toggleDetailFavorite(this)">
                    <i class="<?= $isFavorite ? 'fa-solid' : 'fa-regular' ?> fa-heart" aria-hidden="true"></i>
                    <span><?= $isFavorite ? 'Đã lưu sản phẩm' : 'Lưu sản phẩm' ?></span>
                </button>
            </div>

            <div class="promise-grid" aria-label="Thông tin mua hàng">
                <a class="promise-card" href="/cakev0/pages/shipping.php">
                    <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                    <strong>Giao hàng rõ ràng</strong>
                    <span>Phí được báo trước khi giao</span>
                </a>
                <a class="promise-card" href="/cakev0/pages/payment-policy.php">
                    <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                    <strong>Thanh toán linh hoạt</strong>
                    <span>COD, chuyển khoản, VNPAY</span>
                </a>
                <a class="promise-card" href="/cakev0/pages/exchanges-policy.php">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                    <strong>Hỗ trợ đổi trả</strong>
                    <span>Yêu cầu trong vòng 24 giờ</span>
                </a>
            </div>
        </div>
    </section>

    <section class="section-card" id="description" aria-labelledby="descriptionTitle">
        <div class="section-heading-row">
            <div>
                <span class="section-eyebrow">Câu chuyện chiếc bánh</span>
                <h2 class="section-title" id="descriptionTitle">Thông tin sản phẩm</h2>
            </div>
        </div>
        <div class="description-content">
            <?= $safeDescriptionHtml !== '' ? $safeDescriptionHtml : '<p>Thông tin chi tiết sẽ được cập nhật sớm.</p>' ?>
        </div>
    </section>

    <section class="section-card" id="reviews" aria-labelledby="reviewsTitle">
        <div class="section-heading-row">
            <div>
                <span class="section-eyebrow">Từ khách hàng</span>
                <h2 class="section-title" id="reviewsTitle">Đánh giá sản phẩm</h2>
            </div>
        </div>

        <div class="review-overview">
            <div class="review-score">
                <strong><?= $reviewTotal > 0 ? number_format($averageRating, 1) : '0.0' ?></strong>
                <span><?= $reviewTotal ?> đánh giá</span>
            </div>
            <div class="review-bars" aria-label="Phân bố đánh giá">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <?php $barPercent = $reviewTotal > 0 ? ($reviewCounts[$i] / $reviewTotal) * 100 : 0; ?>
                    <div class="review-bar">
                        <span><?= $i ?> sao</span>
                        <span class="review-bar-track"><span class="review-bar-fill" style="width: <?= number_format($barPercent, 2, '.', '') ?>%"></span></span>
                        <span><?= $reviewCounts[$i] ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="review-filters" aria-label="Lọc đánh giá">
            <button type="button" class="review-filter-btn active" data-rating="all" aria-pressed="true">Tất cả (<?= $reviewTotal ?>)</button>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <button type="button" class="review-filter-btn" data-rating="<?= $i ?>" aria-pressed="false"><?= $i ?> sao (<?= $reviewCounts[$i] ?>)</button>
            <?php endfor; ?>
        </div>

        <div class="review-list">
            <?php if (empty($reviews)): ?>
                <p class="review-copy">Chưa có đánh giá cho sản phẩm này.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <article class="review-item" data-rating="<?= (int) $review['rating'] ?>">
                        <div class="review-head">
                            <div>
                                <span class="review-name"><?= htmlspecialchars($review['name']) ?></span>
                                <?php if (!empty($review['created_at'])): ?>
                                    <time class="review-date" datetime="<?= htmlspecialchars(date('c', strtotime($review['created_at']))) ?>">
                                        <?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                            <span class="review-stars" aria-label="<?= (int) $review['rating'] ?> trên 5 sao">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?= $i <= (int) $review['rating'] ? 'fa-solid' : 'fa-regular' ?> fa-star" aria-hidden="true"></i>
                                <?php endfor; ?>
                            </span>
                        </div>
                        <p class="review-copy"><?= htmlspecialchars($review['content']) ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="review-empty-filter" id="reviewEmptyFilter" hidden aria-live="polite">Chưa có đánh giá ở mức sao này.</p>
    </section>

    <section class="section-card" aria-labelledby="relatedTitle">
        <div class="section-heading-row">
            <div>
                <span class="section-eyebrow">Có thể bạn sẽ thích</span>
                <h2 class="section-title" id="relatedTitle">Sản phẩm liên quan</h2>
            </div>
            <a class="product-breadcrumb" href="/cakev0/pages/product.php?loai=<?= urlencode($selected['loai']) ?>">Xem tất cả <?= htmlspecialchars($categoryLabel) ?></a>
        </div>
        <?php if (empty($related)): ?>
            <p>Chưa có sản phẩm liên quan.</p>
        <?php else: ?>
            <div class="related-grid">
                <?php foreach ($related as $item):
                    $relatedSlug = !empty($item['slug']) ? $item['slug'] : slugify($item['ten_banh'], (int) $item['id']);
                    $relatedHasSale = !empty($item['gia_khuyen_mai']);
                    $relatedPrice = $relatedHasSale ? $item['gia_khuyen_mai'] : $item['gia'];
                ?>
                    <a class="related-card" href="/cakev0/product/<?= urlencode($relatedSlug) ?>">
                        <img src="<?= imgPath($item['hinh_anh']) ?>" alt="<?= htmlspecialchars($item['ten_banh']) ?>" width="480" height="480" loading="lazy" decoding="async">
                        <div class="related-name"><?= htmlspecialchars($item['ten_banh']) ?></div>
                        <div class="related-price">
                            <span><?= number_format($relatedPrice) ?> VNĐ</span>
                            <?php if ($relatedHasSale): ?><del><?= number_format($item['gia']) ?> VNĐ</del><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</main>

<div class="mobile-buy-bar" id="mobileBuyBar" aria-label="Mua nhanh">
    <span class="mobile-buy-price"><?= number_format($finalPrice) ?> VNĐ</span>
    <button class="mobile-buy-btn" type="button" id="mobileBuyButton" <?= $inStock ? '' : 'disabled' ?>>
        <?= $inStock ? 'Thêm vào giỏ' : 'Hết hàng' ?>
    </button>
</div>

<?php include '../includes/footer.html'; ?>

<script>
const gallery = document.querySelector('.detail-gallery');
const dots = document.querySelectorAll('.detail-dot');
const prevBtn = document.querySelector('.detail-nav-btn.prev');
const nextBtn = document.querySelector('.detail-nav-btn.next');

if (gallery) {
    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;
    let activeIndex = 0;
    let rafId = null;

    const updateNavButtons = () => {
        if (!prevBtn || !nextBtn) return;
        const maxIndex = Math.max(0, dots.length - 1);
        prevBtn.classList.toggle('is-hidden', activeIndex <= 0);
        nextBtn.classList.toggle('is-hidden', activeIndex >= maxIndex);
    };

    const setActiveDot = (index) => {
        dots.forEach((dot) => {
            dot.classList.remove('active');
            dot.setAttribute('aria-current', 'false');
        });
        if (dots[index]) {
            dots[index].classList.add('active');
            dots[index].setAttribute('aria-current', 'true');
        }
        activeIndex = index;
        updateNavButtons();
    };

    const scrollToIndex = (index) => {
        const width = gallery.clientWidth || 1;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        gallery.scrollTo({ left: width * index, behavior: reduceMotion ? 'auto' : 'smooth' });
        setActiveDot(index);
    };

    const endDrag = () => {
        isDown = false;
        gallery.classList.remove('is-dragging');
    };

    gallery.addEventListener('mousedown', (e) => {
        isDown = true;
        gallery.classList.add('is-dragging');
        startX = e.pageX - gallery.offsetLeft;
        scrollLeft = gallery.scrollLeft;
    });

    gallery.addEventListener('mouseleave', endDrag);
    gallery.addEventListener('mouseup', endDrag);

    gallery.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - gallery.offsetLeft;
        const walk = x - startX;
        gallery.scrollLeft = scrollLeft - walk;
    });

    gallery.addEventListener('dragstart', (e) => e.preventDefault());

    gallery.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        const maxIndex = Math.max(0, Math.max(dots.length, gallery.querySelectorAll('img').length) - 1);
        const nextIndex = e.key === 'ArrowLeft'
            ? Math.max(0, activeIndex - 1)
            : Math.min(maxIndex, activeIndex + 1);
        scrollToIndex(nextIndex);
    });

    gallery.addEventListener('scroll', () => {
        if (rafId) return;
        rafId = requestAnimationFrame(() => {
            const width = gallery.clientWidth || 1;
            const nextIndex = Math.round(gallery.scrollLeft / width);
            if (nextIndex !== activeIndex) {
                setActiveDot(nextIndex);
            }
            rafId = null;
        });
    });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const index = parseInt(dot.getAttribute('data-index') || '0', 10);
            scrollToIndex(index);
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            const nextIndex = Math.max(0, activeIndex - 1);
            scrollToIndex(nextIndex);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const maxIndex = Math.max(0, dots.length - 1);
            const nextIndex = Math.min(maxIndex, activeIndex + 1);
            scrollToIndex(nextIndex);
        });
    }

    updateNavButtons();

    window.addEventListener('resize', () => {
        scrollToIndex(activeIndex);
    });
}

const filterButtons = document.querySelectorAll('.review-filter-btn');
const reviewItems = document.querySelectorAll('.review-item');
const reviewEmptyFilter = document.getElementById('reviewEmptyFilter');
filterButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        const rating = btn.getAttribute('data-rating');
        let visibleCount = 0;
        filterButtons.forEach((item) => {
            item.classList.remove('active');
            item.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        reviewItems.forEach((item) => {
            if (rating === 'all' || item.getAttribute('data-rating') === rating) {
                item.hidden = false;
                visibleCount += 1;
            } else {
                item.hidden = true;
            }
        });
        if (reviewEmptyFilter) reviewEmptyFilter.hidden = visibleCount > 0 || reviewItems.length === 0;
    });
});

const detailQty = document.getElementById('detailQty');
document.querySelectorAll('[data-qty-step]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!detailQty || detailQty.disabled) return;
        const min = parseInt(detailQty.min || '1', 10);
        const max = parseInt(detailQty.max || '999', 10);
        const step = parseInt(button.dataset.qtyStep || '0', 10);
        const current = parseInt(detailQty.value || String(min), 10);
        detailQty.value = String(Math.min(max, Math.max(min, current + step)));
    });
});

if (detailQty) {
    detailQty.addEventListener('change', () => {
        const min = parseInt(detailQty.min || '1', 10);
        const max = parseInt(detailQty.max || '999', 10);
        const current = parseInt(detailQty.value || String(min), 10);
        detailQty.value = String(Math.min(max, Math.max(min, Number.isFinite(current) ? current : min)));
    });
}

function addDetailToCart(id, button) {
    const qtyInput = document.getElementById('detailQty');
    const qty = Math.max(1, parseInt(qtyInput?.value || '1', 10));
    const mobileButton = document.getElementById('mobileBuyButton');
    const actionButtons = [button, mobileButton].filter(Boolean);
    const originalStates = actionButtons.map((item) => ({ item, html: item.innerHTML, disabled: item.disabled }));
    actionButtons.forEach((item) => {
        item.disabled = true;
        item.setAttribute('aria-busy', 'true');
        item.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Đang thêm...';
    });

    const goLogin = () => {
        const redirect = encodeURIComponent(window.location.pathname + window.location.search);
        setTimeout(() => {
            window.location.href = `/cakev0/pages/login.php?redirect=${redirect}`;
        }, 500);
    };

    fetch('/cakev0/pages/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&banh_id=${id}&qty=${qty}`
    })
    .then(async (r) => {
        const raw = await r.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            const looksLikeLoginRedirect = r.redirected || /login\.php/i.test(r.url || '') || /<html|<!doctype/i.test(raw);
            if (looksLikeLoginRedirect) {
                return { success: false, require_login: true, message: 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.' };
            }
            return { success: false, message: 'Không thêm được, vui lòng thử lại!' };
        }
    })
    .then(d => {
        if (d.success) {
            if (window.showToast) {
                window.showToast('Đã thêm vào giỏ hàng!', 'success');
            }
            if (typeof d.cart_count !== 'undefined') {
                if (window.setCartBadge) {
                    window.setCartBadge(d.cart_count);
                }
            }
        } else if (window.showToast) {
            window.showToast(d.message || 'Không thêm được, vui lòng thử lại!', 'error');
            if (d.require_login) {
                goLogin();
            }
        }
    })
    .catch(() => {
        if (window.showToast) {
            window.showToast('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.', 'error');
        }
        goLogin();
    })
    .finally(() => {
        originalStates.forEach(({ item, html, disabled }) => {
            item.disabled = disabled;
            item.removeAttribute('aria-busy');
            item.innerHTML = html;
        });
    });
}

function toggleDetailFavorite(button) {
    const productId = parseInt(button.dataset.productId || '0', 10);
    if (!productId) return;

    fetch('/cakev0/pages/favorites.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle&banh_id=${productId}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            if (window.showToast) {
                window.showToast(d.message || 'Không thể cập nhật danh sách yêu thích.', 'error');
            }
            if (d.require_login) {
                window.location.href = '/cakev0/pages/login.php';
            }
            return;
        }

        const isFav = !!d.is_favorite;
        const icon = button.querySelector('i');
        const text = button.querySelector('span');
        button.classList.toggle('is-active', isFav);
        button.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        if (icon) {
            icon.className = (isFav ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        }
        if (text) {
            text.textContent = isFav ? 'Đã lưu' : 'Lưu sản phẩm';
        }

        if (typeof d.favorite_count !== 'undefined' && window.setFavoriteBadge) {
            window.setFavoriteBadge(d.favorite_count);
        }

        if (window.showToast) {
            window.showToast(d.message || (isFav ? 'Đã lưu sản phẩm.' : 'Đã bỏ lưu sản phẩm.'), 'success');
        }
    })
    .catch(() => {
        if (window.showToast) {
            window.showToast('Lỗi kết nối máy chủ!', 'error');
        }
    });
}

const mainAddButton = document.getElementById('mainAddToCart');
const mobileBuyBar = document.getElementById('mobileBuyBar');
const mobileBuyButton = document.getElementById('mobileBuyButton');
const pageFooter = document.querySelector('footer');

if (mainAddButton && mobileBuyBar && mobileBuyButton) {
    let mainActionVisible = true;
    let footerVisible = false;
    const updateMobileBar = () => {
        const mobileViewport = window.matchMedia('(max-width: 700px)').matches;
        mobileBuyBar.classList.toggle('is-visible', mobileViewport && !mainActionVisible && !footerVisible);
    };

    mobileBuyButton.addEventListener('click', () => mainAddButton.click());

    if ('IntersectionObserver' in window) {
        const mainObserver = new IntersectionObserver((entries) => {
            mainActionVisible = entries[0]?.isIntersecting ?? true;
            updateMobileBar();
        }, { threshold: 0.2 });
        mainObserver.observe(mainAddButton);

        if (pageFooter) {
            const footerObserver = new IntersectionObserver((entries) => {
                footerVisible = entries[0]?.isIntersecting ?? false;
                updateMobileBar();
            }, { threshold: 0.02 });
            footerObserver.observe(pageFooter);
        }
    }

    window.addEventListener('resize', updateMobileBar, { passive: true });
    updateMobileBar();
}
</script>

<?php $conn->close(); ?>
</body>
</html>
