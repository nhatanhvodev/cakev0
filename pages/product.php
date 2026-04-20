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

    $sql = "SELECT b.*, p.gia_khuyen_mai,\n                   COALESCE(p.gia_khuyen_mai, b.gia) AS final_price,\n                   {$ratingSelectSql}\n            FROM banh b\n            LEFT JOIN promotions p ON b.id=p.banh_id\n            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?\n            {$ratingJoinSql}\n            WHERE {$whereSql}\n            {$orderBySql}";

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
            $categoryParts[] = "b.loai = ?";
            $categoryParams[] = $categoryMap[$term];
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

    if ($termCount === 1 && !empty($categoryParts)) {
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
body {
    background: #ffffff;
    color: #272727;
    font-family: 'Poppins', sans-serif;
    margin: 0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
}

.page-content {
    flex: 1;
}

.products-wrap {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    padding: 30px 20px 40px;
    max-width: 1180px;
    margin: 20px auto 10px;
    align-items: start;
}

@media(max-width: 900px) {
    .products-wrap { grid-template-columns: 1fr; }
}

.product-content {
    background: #fff;
    border-radius: 0;
    border: 1px solid #f3e0be;
    box-shadow: 0 18px 36px rgba(74, 29, 31, 0.08);
    padding: 22px 22px 28px;
}

.product-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid #f3e0be;
    border-radius: 10px;
    background: #fff9f1;
    padding: 14px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.product-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 190px;
}

.filter-field label {
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    color: #4a1d1f;
}

.filter-field select {
    border: 1px solid #e4c997;
    border-radius: 10px;
    padding: 10px 12px;
    background: #fff;
    color: #2f1415;
    font-size: 14px;
    font-family: inherit;
}

.reset-filter {
    color: #4a1d1f;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #4a1d1f;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px;
}

.reset-filter:hover {
    background: #4a1d1f;
    color: #fbedcd;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    align-content: start;
}

@media (max-width: 1120px) {
    .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
    .products-wrap { grid-template-columns: 1fr; }

    .product-content {
        padding: 18px;
        border-radius: 0;
    }

    .product-card img {
        aspect-ratio: 1 / 1;
    }
}

@media (max-width: 520px) {
    .product-filter-form {
        width: 100%;
    }

    .filter-field {
        min-width: 100%;
    }
}

.product-card {
    background: #fff;
    border-radius: 0;
    padding: 14px;
    border: 1px solid #f3e0be;
    box-shadow: 0 10px 22px rgba(74, 29, 31, 0.08);
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 36px rgba(74, 29, 31, 0.16);
}

.product-card img {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    border-radius: 14px;
}

.product-link {
    color: inherit;
    display: block;
}

.product-name {
    font-weight: 600;
    margin: 10px 0 6px;
    font-size: 15px;
    min-height: 42px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #222;
}

.price { margin-bottom: 10px; }
.price del { color: #bbb; font-size: 13px; margin-right: 4px; }
.price .current-price { color: #4a1d1f; font-weight: 700; font-size: 16px; }
.discount-rate {
    margin-left: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #b42318;
}

.add-btn {
    background: #4a1d1f;
    color: #fbedcd;
    border: none;
    padding: 11px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    line-height: 1.2;
    white-space: nowrap;
}

.add-btn:hover {
    background: #2f1415;
    box-shadow: 0 12px 20px rgba(74, 29, 31, 0.25);
}

.product-actions {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.product-actions .add-btn {
    flex: 1;
}

.fav-btn {
    width: 44px;
    min-width: 44px;
    height: 44px;
    border: 1px solid #eecfa6;
    border-radius: 10px;
    background: #fff9f1;
    color: #8a5a3a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.fav-btn:hover {
    background: #fcebd5;
}

.fav-btn.is-active {
    background: #ffefef;
    border-color: #f3b8b8;
    color: #b42318;
}

@media (max-width: 640px) {
    .product-actions {
        gap: 6px;
    }

    .product-actions .add-btn {
        min-height: 40px;
        padding: 8px 10px;
        font-size: 13px;
    }

    .product-actions .add-btn i {
        display: none;
    }

    .fav-btn {
        width: 40px;
        min-width: 40px;
        height: 40px;
    }
}

.hidden { display: none; }

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

<main class="page-content">
<div class="products-wrap">
    <section class="product-content">
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
        ?>
        <div class="product-toolbar">
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
            <a class="reset-filter" href="<?= htmlspecialchars($resetUrl) ?>">Xóa lọc</a>
        </div>

        <?php foreach ($san_pham as $k => $ds): ?>
            <div id="<?= $k ?>" class="cat <?= $k == $loai_active ? '' : 'hidden' ?>">
                <h2 style="color:#4a1d1f; margin-bottom:18px;"><?= $ten_loai[$k] ?></h2>
                <div class="product-grid">
                    <?php if (!$ds): ?>
                        <p style="color:#888">Không có sản phẩm phù hợp bộ lọc.</p>
                    <?php endif; ?>
                    <?php foreach ($ds as $p): ?>
                        <?php $isFavorite = isset($favoriteIds[(int) $p['id']]); ?>
                        <div class="product-card">
                            <?php $slug = !empty($p['slug']) ? $p['slug'] : slugify($p['ten_banh'], (int) $p['id']); ?>
                            <a class="product-link" href="/cakev0/product/<?= urlencode($slug) ?>">
                                <img src="<?= img($p['hinh_anh']) ?>" alt="<?= htmlspecialchars($p['ten_banh']) ?>">
                                <div class="product-name"><?= htmlspecialchars($p['ten_banh']) ?></div>
                            </a>
                            <div class="price">
                                <?php if ($p['gia_khuyen_mai']): ?>
                                    <del><?= number_format($p['gia']) ?>đ</del>
                                    <span class="current-price"><?= number_format($p['gia_khuyen_mai']) ?>đ</span>
                                    <?php if ($p['gia'] > 0): ?>
                                        <?php $discount = (int) round(100 - (($p['gia_khuyen_mai'] / $p['gia']) * 100)); ?>
                                        <span class="discount-rate">-<?= $discount ?>%</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="current-price"><?= number_format($p['gia']) ?>đ</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-actions">
                                <button class="add-btn"
                                        onclick="addCartQuick(<?= $p['id'] ?>)">
                                    <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ
                                </button>
                                <button type="button"
                                        class="fav-btn <?= $isFavorite ? 'is-active' : '' ?>"
                                        data-product-id="<?= (int) $p['id'] ?>"
                                        aria-label="Yêu thích sản phẩm"
                                        onclick="toggleFavorite(this)">
                                    <i class="<?= $isFavorite ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>

</main>

<?php include '../includes/footer.html'; ?>

<button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Len dau trang">^</button>

<script>
function addCartQuick(productId) {
    fetch('/cakev0/pages/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&banh_id=${productId}&qty=1`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.showToast('🧁 Đã thêm vào giỏ hàng!', 'success');
            // Dùng cart_count chính xác từ server (số loại sản phẩm)
            if (typeof d.cart_count !== 'undefined') {
                window.setCartBadge(d.cart_count);
            }
        } else {
            window.showToast('Không thêm được, vui lòng thử lại!', 'error');
        }
    })
    .catch(() => window.showToast('Lỗi kết nối máy chủ!', 'error'));
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
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>

<?php $conn->close(); ?>