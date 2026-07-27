<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config/connect.php';

$pageTitle = 'Sản phẩm';
$isLoggedIn = isset($_SESSION['user_id']);

$ds_loai = ['ngot', 'man', 'mi', 'kem'];
$ten_loai = [
    'ngot' => 'Bánh ngọt',
    'man'  => 'Bánh mặn',
    'mi'   => 'Bánh mì',
    'kem'  => 'Bánh kem',
    'khuyenmai' => 'Bánh đang khuyến mãi'
];

$loai_active = $_GET['loai'] ?? 'ngot';
$search = trim($_GET['search'] ?? '');
$sort_active = $_GET['sort'] ?? 'default';
$rating_active = $_GET['rating'] ?? 'all';

$allowed_loai = array_merge($ds_loai, ['khuyenmai']);
if (!in_array($loai_active, $allowed_loai, true)) {
    $loai_active = 'ngot';
}

$sort_labels = [
    'default' => 'Mặc định',
    'price_asc' => 'Giá: Thấp đến cao',
    'price_desc' => 'Giá: Cao đến thấp'
];

$rating_labels = [
    'all' => 'Tất cả đánh giá',
    '5' => 'Từ 5 sao',
    '4' => 'Từ 4 sao',
    '3' => 'Từ 3 sao',
    '2' => 'Từ 2 sao',
    '1' => 'Từ 1 sao'
];

if (!isset($sort_labels[$sort_active])) {
    $sort_active = 'default';
}

if (!isset($rating_labels[$rating_active])) {
    $rating_active = 'all';
}

$min_rating = $rating_active === 'all' ? null : (int) $rating_active;
$san_pham = [];
$favoriteIds = [];
$favoritesTableReady = false;
$reviewsTableReady = false;
$today = date('Y-m-d');

function safeTransliterate(string $value): string {
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted === false || $converted === '') {
        return $value;
    }
    return $converted;
}

function normalizeSearchTerm(string $value): string {
    $slug = safeTransliterate($value);
    $slug = strtolower($slug ?: $value);
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug);
    return $slug ?? '';
}

function getProductOrderByClause(string $sortOption): string {
    if ($sortOption === 'price_asc') {
        return 'ORDER BY final_price ASC, b.id ASC';
    }
    if ($sortOption === 'price_desc') {
        return 'ORDER BY final_price DESC, b.id DESC';
    }
    return 'ORDER BY b.id ASC';
}

function appendRatingFilter(string &$whereSql, string &$types, array &$params, ?int $minRating, bool $reviewsTableReady): void {
    if ($minRating === null) {
        return;
    }

    if (!$reviewsTableReady) {
        $whereSql .= ' AND 1=0';
        return;
    }

    $whereSql .= ' AND COALESCE(rv.avg_rating, 0) >= ?';
    $types .= 'i';
    $params[] = $minRating;
}

function fetchProducts(
    mysqli $conn,
    string $today,
    bool $reviewsTableReady,
    string $whereSql,
    string $types,
    array $params,
    string $orderBySql
): array {
    $ratingSelectSql = $reviewsTableReady
        ? 'COALESCE(rv.avg_rating, 0) AS avg_rating, COALESCE(rv.review_count, 0) AS review_count'
        : '0 AS avg_rating, 0 AS review_count';

    $ratingJoinSql = $reviewsTableReady
        ? "LEFT JOIN (\n                SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count\n                FROM product_reviews\n                GROUP BY product_id\n            ) rv ON rv.product_id = b.id"
        : '';

    $sql = "SELECT b.*, p.gia_khuyen_mai,\n                   COALESCE(p.gia_khuyen_mai, b.gia) AS final_price,\n                   {$ratingSelectSql}\n            FROM banh b\n            LEFT JOIN promotions p ON b.id=p.banh_id\n            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?\n            {$ratingJoinSql}\n            WHERE b.is_hidden = 0 AND ({$whereSql})\n            {$orderBySql}";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $bindTypes = 'ss' . $types;
    $bindParams = array_merge([$today, $today], $params);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

if ($conn) {
    $favoriteTableResult = $conn->query("SHOW TABLES LIKE 'favorites'");
    if ($favoriteTableResult) {
        $favoritesTableReady = $favoriteTableResult->num_rows > 0;
    }

    $reviewsTableResult = $conn->query("SHOW TABLES LIKE 'product_reviews'");
    if ($reviewsTableResult) {
        $reviewsTableReady = $reviewsTableResult->num_rows > 0;
    }
}

$order_by_sql = getProductOrderByClause($sort_active);

if ($search !== '') {
    $search = preg_replace('/\s+/', ' ', $search);
    $terms = array_values(array_filter(preg_split('/\s+/', $search)));
    $normalizedTerms = array_map('normalizeSearchTerm', $terms);
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

    $normalizedPhrase = implode('', $normalizedTerms);
    $matchedCategory = $categoryMap[$normalizedPhrase] ?? null;

    $whereParts = [];
    $categoryParts = [];
    $keywordParts = [];
    $categoryParams = [];
    $keywordParams = [];
    $params = [];
    $types = '';
    $termCount = count($terms);

    foreach ($normalizedTerms as $index => $term) {
        if ($termCount === 1 && isset($categoryMap[$term])) {
            $matchedCategory = $categoryMap[$term];
        }
        if (!empty($terms[$index])) {
            $keywordParts[] = "(b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ? OR b.mo_ta COLLATE utf8mb4_unicode_ci LIKE ?)";
            $keywordParams[] = '%' . $terms[$index] . '%';
            $keywordParams[] = '%' . $terms[$index] . '%';
        }
    }

    if (count($keywordParts) > 1) {
        $whereParts[] = '(' . implode(' AND ', $keywordParts) . ')';
        $params = array_merge($params, $keywordParams);
        $types .= str_repeat('s', count($keywordParams));
    } elseif (count($keywordParts) === 1) {
        $whereParts[] = $keywordParts[0];
        $params = array_merge($params, $keywordParams);
        $types .= str_repeat('s', count($keywordParams));
    }

    if ($matchedCategory !== null) {
        $categoryParts[] = "b.loai = ?";
        $categoryParams[] = $matchedCategory;
    }

    if (!empty($categoryParts)) {
        $whereParts = array_merge($whereParts, $categoryParts);
        $params = array_merge($params, $categoryParams);
        $types .= str_repeat('s', count($categoryParams));
    }

    if (empty($whereParts)) {
        $whereParts[] = "(b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ? OR b.mo_ta COLLATE utf8mb4_unicode_ci LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ss';
    }

    $whereSql = '(' . implode(' OR ', $whereParts) . ')';
    appendRatingFilter($whereSql, $types, $params, $min_rating, $reviewsTableReady);

    $san_pham['search'] = fetchProducts(
        $conn,
        $today,
        $reviewsTableReady,
        $whereSql,
        $types,
        $params,
        $order_by_sql
    );
    $ten_loai['search'] = "Kết quả tìm kiếm:";
    $loai_active = 'search';
} else {
    $promotionWhere = 'p.gia_khuyen_mai IS NOT NULL';
    $promotionTypes = '';
    $promotionParams = [];
    appendRatingFilter($promotionWhere, $promotionTypes, $promotionParams, $min_rating, $reviewsTableReady);
    $san_pham['khuyenmai'] = fetchProducts(
        $conn,
        $today,
        $reviewsTableReady,
        $promotionWhere,
        $promotionTypes,
        $promotionParams,
        $order_by_sql
    );

    foreach ($ds_loai as $loai) {
        $categoryWhere = 'b.loai = ?';
        $categoryTypes = 's';
        $categoryParams = [$loai];
        appendRatingFilter($categoryWhere, $categoryTypes, $categoryParams, $min_rating, $reviewsTableReady);
        $san_pham[$loai] = fetchProducts(
            $conn,
            $today,
            $reviewsTableReady,
            $categoryWhere,
            $categoryTypes,
            $categoryParams,
            $order_by_sql
        );
    }
}

if ($isLoggedIn && $favoritesTableReady) {
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT banh_id FROM favorites WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $favoriteIds[(int) $row['banh_id']] = true;
        }
        $stmt->close();
    }
}

function img($path) {
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

function renderStars($avg): string {
    $avg = (float) $avg;
    if ($avg < 0) { $avg = 0.0; }
    if ($avg > 5) { $avg = 5.0; }
    $full = (int) floor($avg);
    $half = ($avg - $full) >= 0.5 ? 1 : 0;
    if ($full + $half > 5) { $half = 0; }
    $empty = 5 - $full - $half;
    if ($empty < 0) { $empty = 0; }
    $html = '';
    $html .= str_repeat('<i class="fa-solid fa-star"></i>', $full);
    if ($half) { $html .= '<i class="fa-solid fa-star-half-stroke"></i>'; }
    $html .= str_repeat('<i class="fa-regular fa-star"></i>', $empty);
    return $html;
}

$activeProducts = $san_pham[$loai_active] ?? [];
$activeProductCount = count($activeProducts);
$activeCategoryLabel = $ten_loai[$loai_active] ?? 'Sản phẩm';
$hasActiveFilters = $sort_active !== 'default' || $rating_active !== 'all' || $search !== '';

$extraLinks = '<link rel="stylesheet" href="/cakev0/assets/css/style.css">';

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
/* ===== Gấu Bakery — Catalog redesign "Warm Artisan" =====
   Palette lock: cream bg, maroon primary, single caramel accent.
   Radius system: cards 18px · controls 12px · chips/heart pill.  */
:root {
    --pc-cream: #FBF6EE;
    --pc-surface: #ffffff;
    --pc-ink: #3A2117;
    --pc-maroon: #4A1D1F;
    --pc-maroon-deep: #2f1415;
    --pc-caramel: #E07B39;
    --pc-caramel-soft: #FFF2E8;
    --pc-sale: #C0392B;
    --pc-gold: #E8A93C;
    --pc-honey: #F3E0BE;
    --pc-line: rgba(74, 29, 31, 0.10);
    --pc-muted: #8a7a70;
    --pc-shadow: 0 14px 30px rgba(74, 29, 31, 0.08);
    --pc-shadow-lg: 0 22px 44px rgba(74, 29, 31, 0.16);
    --pc-radius-card: 18px;
    --pc-radius-ctrl: 12px;
}

body {
    background: var(--pc-cream);
    color: var(--pc-ink);
    font-family: 'Poppins', sans-serif;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
}

.page-content { flex: 1; }

.catalog {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 26px 20px 56px;
    box-sizing: border-box;
}

.catalog :where(h1, h2, h3) { text-wrap: balance; }
.catalog :where(p, li) { text-wrap: pretty; }

.catalog-breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 20px;
    color: var(--pc-muted);
    font-size: 13px;
}

.catalog-breadcrumb a {
    color: var(--pc-maroon);
    font-weight: 600;
    text-decoration: none;
}

.catalog-breadcrumb a:hover { color: var(--pc-caramel); }

.catalog-intro {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 24px;
    margin-bottom: 24px;
}

.catalog-eyebrow {
    display: block;
    margin-bottom: 8px;
    color: var(--pc-caramel);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.catalog-intro h1 {
    max-width: 18ch;
    margin: 0;
    color: var(--pc-maroon);
    font-size: clamp(30px, 5vw, 48px);
    line-height: 1.12;
    letter-spacing: -0.035em;
}

.catalog-intro p {
    max-width: 62ch;
    margin: 12px 0 0;
    color: #6f5d53;
    font-size: 15px;
    line-height: 1.7;
}

.catalog-result {
    min-width: 152px;
    padding: 16px 18px;
    border-radius: 16px;
    background: #fff7ea;
    box-shadow: inset 0 0 0 1px var(--pc-honey);
    color: var(--pc-maroon);
    text-align: right;
}

.catalog-result strong {
    display: block;
    font-size: 26px;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.catalog-result span {
    display: block;
    margin-top: 4px;
    color: var(--pc-muted);
    font-size: 12px;
    font-weight: 600;
}

.mobile-filter-trigger,
.mobile-toolbar-head,
.filter-backdrop { display: none; }

/* ---- Toolbar (scrolls with the page; not sticky so it never covers cards) ---- */
.catalog-toolbar {
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--pc-surface);
    border: 1px solid var(--pc-honey);
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 28px;
    box-shadow: var(--pc-shadow);
}

.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: -12px 0 24px;
}

.active-filters-label {
    color: var(--pc-muted);
    font-size: 12px;
    font-weight: 600;
}

.active-filter {
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--pc-caramel-soft);
    color: var(--pc-maroon);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.active-filter i { color: var(--pc-caramel); }

.chip-row {
    display: flex;
    gap: 9px;
    overflow-x: auto;
    padding-bottom: 2px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.chip-row::-webkit-scrollbar { display: none; }

.chip {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 44px;
    padding: 9px 15px;
    box-sizing: border-box;
    border-radius: 999px;
    border: 1px solid var(--pc-honey);
    background: var(--pc-surface);
    color: var(--pc-maroon);
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.2s;
}

.chip:hover {
    border-color: var(--pc-caramel);
    transform: translateY(-1px);
}

.chip .chip-count {
    font-size: 12px;
    font-weight: 700;
    background: var(--pc-caramel-soft);
    color: var(--pc-caramel);
    border-radius: 999px;
    padding: 1px 8px;
}

.chip.is-active {
    background: var(--pc-caramel);
    border-color: var(--pc-caramel);
    color: #fff;
}

.chip.is-active .chip-count {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}

.filter-bar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.product-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-field label {
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--pc-muted);
}

.filter-field select {
    min-height: 44px;
    border: 1px solid var(--pc-honey);
    border-radius: var(--pc-radius-ctrl);
    padding: 9px 12px;
    background: var(--pc-surface);
    color: var(--pc-ink);
    font-size: 13.5px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
}

.filter-field select:focus {
    outline: 2px solid var(--pc-caramel);
    outline-offset: 1px;
    border-color: var(--pc-caramel);
}

.reset-filter {
    align-self: flex-end;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--pc-maroon);
    font-weight: 600;
    font-size: 13.5px;
    text-decoration: none;
    border: 1px solid var(--pc-honey);
    border-radius: var(--pc-radius-ctrl);
    padding: 9px 14px;
    min-height: 44px;
    box-sizing: border-box;
    background: var(--pc-surface);
    transition: background 0.2s, color 0.2s, border-color 0.2s;
}

.reset-filter:hover {
    background: var(--pc-maroon);
    color: var(--pc-cream);
    border-color: var(--pc-maroon);
}

/* ---- Category header ---- */
.cat-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 4px 0 22px;
}

.cat-head h2 {
    margin: 0;
    color: var(--pc-maroon);
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.01em;
    white-space: nowrap;
}

.cat-head .cat-count {
    font-size: 13px;
    color: var(--pc-muted);
    font-weight: 500;
    white-space: nowrap;
}

.cat-head::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--pc-line);
}

/* ---- Product grid ---- */
.product-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    align-content: start;
}

@media (max-width: 1024px) {
    .product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 760px) {
    .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
}

/* ---- Card ---- */
.product-card {
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--pc-surface);
    border: 1px solid var(--pc-honey);
    border-radius: var(--pc-radius-card);
    padding: 12px 12px 16px;
    box-shadow: var(--pc-shadow);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.product-card.is-sold-out .card-media img {
    filter: saturate(0.65);
}

.card-media {
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    background: var(--pc-caramel-soft);
}

.card-media img {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    transition: transform 0.45s ease;
}

@media (hover: hover) and (pointer: fine) {
    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--pc-shadow-lg);
    }

    .product-card:hover .card-media img { transform: scale(1.04); }
}

.badge-sale {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 2;
    background: var(--pc-sale);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 999px;
    box-shadow: 0 4px 10px rgba(192, 57, 43, 0.3);
}

.fav-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 2;
    width: 44px;
    min-width: 44px;
    height: 44px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    color: var(--pc-maroon);
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(74, 29, 31, 0.15);
    transition: transform 0.2s, background 0.2s, color 0.2s;
}

.fav-btn:hover { transform: scale(1.1); background: #fff; }
.fav-btn.is-active { background: #fff; color: var(--pc-sale); }

.card-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding-top: 12px;
}

.product-link {
    color: inherit;
    text-decoration: none;
    display: block;
}

.product-name {
    font-weight: 600;
    font-size: 14.5px;
    line-height: 1.35;
    margin: 0 0 8px;
    color: var(--pc-ink);
    min-height: 40px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-link:hover .product-name { color: var(--pc-maroon); }

.rating-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
    min-height: 18px;
}

.stars {
    display: inline-flex;
    gap: 1px;
    color: var(--pc-gold);
    font-size: 12.5px;
}

.stars .fa-regular { color: #d8cdbf; }

.rating-count, .rating-empty {
    font-size: 12px;
    color: var(--pc-muted);
}

.stock-row {
    min-height: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: -2px 0 8px;
}

.stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #25663f;
    font-size: 12px;
    font-weight: 700;
}

.stock-pill::before {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2f8a54;
    content: "";
}

.stock-pill.is-low { color: #9a4e1d; }
.stock-pill.is-low::before { background: var(--pc-caramel); }
.stock-pill.is-out { color: #9b3131; }
.stock-pill.is-out::before { background: #b42318; }

.price {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.price .current-price {
    color: var(--pc-maroon);
    font-weight: 700;
    font-size: 16.5px;
}

.price del { color: var(--pc-muted); font-size: 13px; }

.price .discount-rate {
    font-size: 12px;
    font-weight: 700;
    color: var(--pc-sale);
}

.card-foot { margin-top: auto; }

.add-btn {
    width: 100%;
    background: var(--pc-maroon);
    color: var(--pc-cream);
    border: none;
    border-radius: var(--pc-radius-ctrl);
    padding: 11px;
    font-weight: 600;
    font-size: 13.5px;
    font-family: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    line-height: 1.2;
    transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
}

.add-btn:hover {
    background: var(--pc-maroon-deep);
    box-shadow: 0 10px 20px rgba(74, 29, 31, 0.22);
}

.add-btn:active { transform: translateY(1px); }

.add-btn:disabled {
    background: #c8b9af;
    color: #fffaf5;
    cursor: not-allowed;
    box-shadow: none;
}

.catalog :focus-visible {
    outline: 3px solid rgba(224, 123, 57, 0.45);
    outline-offset: 3px;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 48px 20px;
    color: var(--pc-muted);
    font-size: 14px;
}

.empty-state i {
    display: block;
    font-size: 34px;
    color: var(--pc-honey);
    margin-bottom: 12px;
}

/* ---- Entrance fade (opacity only, so :hover transform stays intact) ---- */
@media (prefers-reduced-motion: no-preference) {
    .cat:not(.hidden) .product-card { animation: pcFade 0.45s ease both; }
    .cat:not(.hidden) .product-card:nth-child(2) { animation-delay: 0.04s; }
    .cat:not(.hidden) .product-card:nth-child(3) { animation-delay: 0.08s; }
    .cat:not(.hidden) .product-card:nth-child(4) { animation-delay: 0.12s; }
    .cat:not(.hidden) .product-card:nth-child(5) { animation-delay: 0.16s; }
    .cat:not(.hidden) .product-card:nth-child(6) { animation-delay: 0.20s; }
    .cat:not(.hidden) .product-card:nth-child(7) { animation-delay: 0.24s; }
    .cat:not(.hidden) .product-card:nth-child(8) { animation-delay: 0.28s; }
}

@keyframes pcFade {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.hidden { display: none; }

/* ---- Responsive ---- */
@media (max-width: 600px) {
    .catalog { padding: 18px 14px 44px; }
    .cat-head h2 { font-size: 18px; }
}

@media (max-width: 560px) {
    .catalog-intro {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .catalog-result {
        display: flex;
        align-items: baseline;
        gap: 6px;
        min-width: 0;
        padding: 12px 14px;
        text-align: left;
    }

    .catalog-result strong { font-size: 20px; }

    .mobile-filter-trigger {
        width: 100%;
        min-height: 48px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        padding: 0 16px;
        border: 1px solid var(--pc-honey);
        border-radius: 14px;
        background: var(--pc-surface);
        color: var(--pc-maroon);
        font: 600 14px/1 'Poppins', sans-serif;
        box-shadow: var(--pc-shadow);
        cursor: pointer;
    }

    .mobile-filter-trigger .filter-state {
        color: var(--pc-muted);
        font-size: 12px;
        font-weight: 500;
    }

    .filter-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1990;
        display: block;
        border: 0;
        background: rgba(47, 20, 21, 0.46);
        opacity: 0;
        visibility: hidden;
        transition: opacity 180ms cubic-bezier(0.23, 1, 0.32, 1), visibility 180ms cubic-bezier(0.23, 1, 0.32, 1);
    }

    .filter-backdrop.is-open {
        opacity: 1;
        visibility: visible;
    }

    .catalog-toolbar {
        position: fixed;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 2000;
        max-height: min(84vh, 620px);
        margin: 0;
        padding: 16px 16px 24px;
        border-radius: 24px 24px 0 0;
        overflow-y: auto;
        visibility: hidden;
        transform: translateY(104%);
        transition: transform 240ms cubic-bezier(0.32, 0.72, 0, 1), visibility 240ms cubic-bezier(0.32, 0.72, 0, 1);
    }

    .catalog-toolbar.is-open {
        visibility: visible;
        transform: translateY(0);
    }

    .mobile-toolbar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .mobile-toolbar-head strong {
        color: var(--pc-maroon);
        font-size: 18px;
    }

    .mobile-filter-close {
        width: 44px;
        height: 44px;
        border: 1px solid var(--pc-honey);
        border-radius: 50%;
        background: var(--pc-surface);
        color: var(--pc-maroon);
        cursor: pointer;
    }

    .filter-bar { flex-direction: column; align-items: stretch; }
    .product-filter-form { width: 100%; }
    .filter-field { flex: 1; min-width: 0; }
    .filter-field select { width: 100%; }
    .reset-filter { align-self: stretch; justify-content: center; }

    body.filter-drawer-open { overflow: hidden; }
}

@media (max-width: 390px) {
    .product-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .product-card { padding: 12px; }
    .product-name { min-height: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .catalog *,
    .catalog *::before,
    .catalog *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* ---- Back-to-top (kept mid-right to avoid the chat widget in the corner) ---- */
.scroll-top {
    position: fixed;
    right: 20px;
    top: 80%;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: none;
    background: var(--pc-maroon);
    color: var(--pc-cream);
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
    z-index: 1500;
}

.scroll-top.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(-50%);
}

.scroll-top:hover { background: var(--pc-maroon-deep); }
</style>

<main class="page-content">
<div class="catalog">
    <?php
        $resetParams = [];
        if ($search !== '') {
            $resetParams['search'] = $search;
        } else {
            $resetParams['loai'] = $loai_active;
        }
        $resetUrl = '/cakev0/pages/product.php';
        if (!empty($resetParams)) {
            $resetUrl .= '?' . http_build_query($resetParams);
        }

        // Chips giữ nguyên bộ lọc sort/rating hiện tại khi chuyển danh mục.
        $chipBaseQuery = [];
        if ($sort_active !== 'default') { $chipBaseQuery['sort'] = $sort_active; }
        if ($rating_active !== 'all')   { $chipBaseQuery['rating'] = $rating_active; }
        $chipCats = ['ngot', 'man', 'mi', 'kem', 'khuyenmai'];

        $clearSortParams = $search !== '' ? ['search' => $search] : ['loai' => $loai_active];
        if ($rating_active !== 'all') { $clearSortParams['rating'] = $rating_active; }
        $clearSortUrl = '/cakev0/pages/product.php?' . http_build_query($clearSortParams);

        $clearRatingParams = $search !== '' ? ['search' => $search] : ['loai' => $loai_active];
        if ($sort_active !== 'default') { $clearRatingParams['sort'] = $sort_active; }
        $clearRatingUrl = '/cakev0/pages/product.php?' . http_build_query($clearRatingParams);

        $filterStateParts = [];
        if ($sort_active !== 'default') { $filterStateParts[] = $sort_labels[$sort_active]; }
        if ($rating_active !== 'all') { $filterStateParts[] = $rating_labels[$rating_active]; }
        $mobileFilterState = empty($filterStateParts) ? 'Mặc định' : implode(' · ', $filterStateParts);
    ?>

    <nav class="catalog-breadcrumb" aria-label="Đường dẫn trang">
        <a href="/cakev0/">Trang chủ</a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        <span aria-current="page"><?= htmlspecialchars($search !== '' ? 'Tìm kiếm sản phẩm' : $activeCategoryLabel) ?></span>
    </nav>

    <header class="catalog-intro">
        <div>
            <span class="catalog-eyebrow">Bánh tươi mỗi ngày</span>
            <h1><?= $search !== '' ? 'Kết quả cho “' . htmlspecialchars($search) . '”' : htmlspecialchars($activeCategoryLabel) ?></h1>
            <p>
                <?= $search !== ''
                    ? 'Khám phá những sản phẩm phù hợp từ thực đơn hiện có của Gấu Bakery.'
                    : 'Chọn chiếc bánh hợp khẩu vị, xem đánh giá thật và đặt nhanh cho khoảnh khắc ngọt ngào của bạn.' ?>
            </p>
        </div>
        <div class="catalog-result" aria-live="polite">
            <strong><?= $activeProductCount ?></strong>
            <span>sản phẩm phù hợp</span>
        </div>
    </header>

    <button class="mobile-filter-trigger" type="button" id="mobileFilterTrigger" aria-expanded="false" aria-controls="catalogFilters">
        <span><i class="fa-solid fa-sliders" aria-hidden="true"></i> Bộ lọc &amp; sắp xếp</span>
        <span class="filter-state"><?= htmlspecialchars($mobileFilterState) ?></span>
    </button>
    <button class="filter-backdrop" type="button" id="filterBackdrop" aria-label="Đóng bộ lọc" tabindex="-1"></button>

    <section class="catalog-toolbar" id="catalogFilters" aria-label="Bộ lọc và sắp xếp sản phẩm" tabindex="-1">
        <div class="mobile-toolbar-head">
            <strong id="catalogFilterTitle">Lọc sản phẩm</strong>
            <button class="mobile-filter-close" type="button" id="mobileFilterClose" aria-label="Đóng bộ lọc">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <nav class="chip-row" aria-label="Danh mục sản phẩm">
            <?php foreach ($chipCats as $c): ?>
                <?php
                    $chipUrl = '/cakev0/pages/product.php?' . http_build_query(array_merge(['loai' => $c], $chipBaseQuery));
                    $chipActive = ($search === '' && $loai_active === $c);
                    $chipCount = isset($san_pham[$c]) ? count($san_pham[$c]) : null;
                ?>
                <a class="chip <?= $chipActive ? 'is-active' : '' ?>" href="<?= htmlspecialchars($chipUrl) ?>">
                    <?= htmlspecialchars($ten_loai[$c]) ?>
                    <?php if ($chipCount !== null): ?>
                        <span class="chip-count"><?= $chipCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="filter-bar">
            <form method="get" class="product-filter-form" id="productFilterForm">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php else: ?>
                    <input type="hidden" name="loai" value="<?= htmlspecialchars($loai_active) ?>">
                <?php endif; ?>

                <div class="filter-field">
                    <label for="sort-select">Sắp xếp giá</label>
                    <select id="sort-select" name="sort" onchange="this.form.submit()">
                        <?php foreach ($sort_labels as $sortValue => $sortLabel): ?>
                            <option value="<?= $sortValue ?>" <?= $sort_active === $sortValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sortLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="rating-select">Lọc theo đánh giá</label>
                    <select id="rating-select" name="rating" onchange="this.form.submit()">
                        <?php foreach ($rating_labels as $ratingValue => $ratingLabel): ?>
                            <option value="<?= $ratingValue ?>" <?= $rating_active === $ratingValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ratingLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="add-btn">Áp dụng</button></noscript>
            </form>
            <a class="reset-filter" href="<?= htmlspecialchars($resetUrl) ?>">
                <i class="fa-solid fa-arrows-rotate"></i> Xóa lọc
            </a>
        </div>
    </section>

    <?php if ($hasActiveFilters): ?>
        <div class="active-filters" aria-label="Bộ lọc đang áp dụng">
            <span class="active-filters-label">Đang áp dụng:</span>
            <?php if ($search !== ''): ?>
                <a class="active-filter" href="/cakev0/pages/product.php">
                    Từ khóa: <?= htmlspecialchars($search) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if ($sort_active !== 'default'): ?>
                <a class="active-filter" href="<?= htmlspecialchars($clearSortUrl) ?>">
                    <?= htmlspecialchars($sort_labels[$sort_active]) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if ($rating_active !== 'all'): ?>
                <a class="active-filter" href="<?= htmlspecialchars($clearRatingUrl) ?>">
                    <?= htmlspecialchars($rating_labels[$rating_active]) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($san_pham as $k => $ds): ?>
        <div id="<?= $k ?>" class="cat <?= $k == $loai_active ? '' : 'hidden' ?>">
            <div class="cat-head">
                <h2><?= htmlspecialchars($ten_loai[$k]) ?></h2>
                <?php if ($ds): ?>
                    <span class="cat-count"><?= count($ds) ?> sản phẩm</span>
                <?php endif; ?>
            </div>
            <div class="product-grid">
                <?php if (!$ds): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-cookie-bite"></i>
                        Không có sản phẩm phù hợp bộ lọc.
                    </div>
                <?php endif; ?>
                <?php foreach ($ds as $p): ?>
                    <?php
                        $isFavorite = isset($favoriteIds[(int) $p['id']]);
                        $slug = !empty($p['slug']) ? $p['slug'] : slugify($p['ten_banh'], (int) $p['id']);
                        $avgRating = (float) ($p['avg_rating'] ?? 0);
                        $reviewCount = (int) ($p['review_count'] ?? 0);
                        $hasSale = !empty($p['gia_khuyen_mai']);
                        $discount = ($hasSale && $p['gia'] > 0)
                            ? (int) round(100 - (($p['gia_khuyen_mai'] / $p['gia']) * 100))
                            : 0;
                        $tracksStock = array_key_exists('stock', $p);
                        $stock = $tracksStock ? max(0, (int) $p['stock']) : null;
                        $inStock = !$tracksStock || $stock > 0;
                    ?>
                    <article class="product-card <?= $inStock ? '' : 'is-sold-out' ?>">
                        <div class="card-media">
                            <?php if ($hasSale && $discount > 0): ?>
                                <span class="badge-sale">-<?= $discount ?>%</span>
                            <?php endif; ?>
                            <button type="button"
                                    class="fav-btn <?= $isFavorite ? 'is-active' : '' ?>"
                                    data-product-id="<?= (int) $p['id'] ?>"
                                    aria-label="<?= $isFavorite ? 'Bỏ lưu' : 'Lưu' ?> <?= htmlspecialchars($p['ten_banh']) ?>"
                                    aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
                                    onclick="toggleFavorite(this)">
                                <i class="<?= $isFavorite ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            </button>
                            <a class="product-link" href="/cakev0/product/<?= urlencode($slug) ?>" aria-label="Xem chi tiết <?= htmlspecialchars($p['ten_banh']) ?>">
                                <img src="<?= img($p['hinh_anh']) ?>"
                                     alt="<?= htmlspecialchars($p['ten_banh']) ?>"
                                     loading="lazy"
                                     decoding="async"
                                     width="640"
                                     height="640">
                            </a>
                        </div>

                        <div class="card-body">
                            <a class="product-link" href="/cakev0/product/<?= urlencode($slug) ?>">
                                <div class="product-name"><?= htmlspecialchars($p['ten_banh']) ?></div>
                            </a>

                            <div class="rating-row">
                                <?php if ($reviewCount > 0): ?>
                                    <span class="stars"><?= renderStars($avgRating) ?></span>
                                    <span class="rating-count"><?= number_format($avgRating, 1) ?> (<?= $reviewCount ?>)</span>
                                <?php else: ?>
                                    <span class="stars"><?= renderStars(0) ?></span>
                                    <span class="rating-empty">Chưa có đánh giá</span>
                                <?php endif; ?>
                            </div>

                            <div class="stock-row">
                                <?php if ($tracksStock && !$inStock): ?>
                                    <span class="stock-pill is-out">Tạm hết hàng</span>
                                <?php elseif ($tracksStock && $stock <= 5): ?>
                                    <span class="stock-pill is-low">Chỉ còn <?= $stock ?> sản phẩm</span>
                                <?php endif; ?>
                            </div>

                            <div class="price">
                                <?php if ($hasSale): ?>
                                    <span class="current-price"><?= number_format($p['gia_khuyen_mai']) ?> VNĐ</span>
                                    <del><?= number_format($p['gia']) ?> VNĐ</del>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($p['gia']) ?> VNĐ</span>
                                <?php endif; ?>
                            </div>

                            <div class="card-foot">
                                <button class="add-btn"
                                        type="button"
                                        <?= $inStock ? '' : 'disabled aria-disabled="true"' ?>
                                        onclick="addCartQuick(<?= (int) $p['id'] ?>, this)">
                                    <i class="fa-solid <?= $inStock ? 'fa-cart-plus' : 'fa-clock' ?>" aria-hidden="true"></i>
                                    <?= $inStock ? 'Thêm vào giỏ' : 'Tạm hết hàng' ?>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</main>

<?php include '../includes/footer.html'; ?>

<button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

<script>
function addCartQuick(productId, button) {
    if (button && button.disabled) return;
    const originalButtonHtml = button ? button.innerHTML : '';
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Đang thêm...';
    }

    const goLogin = () => {
        const redirect = encodeURIComponent(window.location.pathname + window.location.search);
        setTimeout(() => {
            window.location.href = `/cakev0/pages/login.php?redirect=${redirect}`;
        }, 500);
    };

    fetch('/cakev0/pages/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&banh_id=${productId}&qty=1`
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
            window.showToast('🧁 Đã thêm vào giỏ hàng!', 'success');
            // Dùng cart_count chính xác từ server (số loại sản phẩm)
            if (typeof d.cart_count !== 'undefined') {
                window.setCartBadge(d.cart_count);
            }
        } else {
            if (d.require_login) {
                window.showToast('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.', 'error');
                goLogin();
                return;
            }
            window.showToast(d.message || 'Không thêm được, vui lòng thử lại!', 'error');
        }
    })
    .catch(() => {
        window.showToast('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.', 'error');
        goLogin();
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = originalButtonHtml;
        }
    });
}

function toggleFavorite(button) {
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
            window.showToast(d.message || 'Không thể cập nhật danh sách yêu thích.', 'error');
            if (d.require_login) {
                window.location.href = '/cakev0/pages/login.php';
            }
            return;
        }

        const isFav = !!d.is_favorite;
        const icon = button.querySelector('i');
        button.classList.toggle('is-active', isFav);
        button.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        button.setAttribute('aria-label', (isFav ? 'Bỏ lưu ' : 'Lưu ') + (button.closest('.product-card')?.querySelector('.product-name')?.textContent || 'sản phẩm'));
        if (icon) {
            icon.className = (isFav ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        }

        if (typeof d.favorite_count !== 'undefined' && window.setFavoriteBadge) {
            window.setFavoriteBadge(d.favorite_count);
        }

        window.showToast(d.message || (isFav ? 'Đã lưu sản phẩm.' : 'Đã bỏ lưu sản phẩm.'), 'success');
    })
    .catch(() => window.showToast('Lỗi kết nối máy chủ!', 'error'));
}
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
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.getElementById('mobileFilterTrigger');
    const panel = document.getElementById('catalogFilters');
    const closeButton = document.getElementById('mobileFilterClose');
    const backdrop = document.getElementById('filterBackdrop');
    if (!trigger || !panel || !closeButton || !backdrop) return;

    const isMobile = () => window.matchMedia('(max-width: 560px)').matches;

    const setOpen = (open, restoreFocus = true) => {
        const shouldOpen = open && isMobile();
        trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        panel.classList.toggle('is-open', shouldOpen);
        backdrop.classList.toggle('is-open', shouldOpen);
        document.body.classList.toggle('filter-drawer-open', shouldOpen);
        if (shouldOpen) {
            closeButton.focus();
        } else if (restoreFocus && isMobile()) {
            trigger.focus();
        }
    };

    trigger.addEventListener('click', () => setOpen(true));
    closeButton.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));

    panel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            return;
        }
        if (event.key !== 'Tab' || !panel.classList.contains('is-open')) return;

        const focusable = [...panel.querySelectorAll('a[href], button:not([disabled]), select:not([disabled]), input:not([disabled])')];
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (!isMobile() && panel.classList.contains('is-open')) {
            setOpen(false, false);
        }
    });
});
</script>

<?php $conn->close(); ?>
