<?php
session_start();
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
$san_pham = [];
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
    $nameParts = [];
    $categoryParams = [];
    $nameParams = [];
    $params = [$today, $today];
    $types = 'ss';
    $termCount = count($terms);

    foreach ($normalizedTerms as $index => $term) {
        if ($termCount === 1 && isset($categoryMap[$term])) {
            $categoryParts[] = "b.loai = ?";
            $categoryParams[] = $categoryMap[$term];
        }
        if (!empty($terms[$index])) {
            $nameParts[] = "b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ?";
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
        $whereParts[] = "b.ten_banh COLLATE utf8mb4_unicode_ci LIKE ?";
        $params[] = '%' . $search . '%';
    }

    $types .= str_repeat('s', count($params) - 2);

    $sql = "SELECT b.*, p.gia_khuyen_mai
            FROM banh b
            LEFT JOIN promotions p ON b.id=p.banh_id
            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
            WHERE " . implode(' OR ', $whereParts);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $san_pham['search'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ten_loai['search'] = "Kết quả tìm kiếm:";
    $loai_active = 'search';
} else {
    $sql = "SELECT b.*, p.gia_khuyen_mai
            FROM banh b
            INNER JOIN promotions p ON b.id=p.banh_id
            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $today, $today);
    $stmt->execute();
    $san_pham['khuyenmai'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($ds_loai as $loai) {
        $sql = "SELECT b.*, p.gia_khuyen_mai
                FROM banh b
                LEFT JOIN promotions p ON b.id=p.banh_id
                AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
                WHERE b.loai=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $today, $today, $loai);
        $stmt->execute();
        $san_pham[$loai] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

function img($path) {
    if (!$path) return '/Cake/assets/img/no-image.jpg';
    if (strpos($path, 'assets/') === false && strpos($path, 'img/') === 0) {
        $path = str_replace('img/', 'assets/img/', $path);
    }
    return '/Cake/' . ltrim($path, '/');
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

$extraLinks = '<link rel="stylesheet" href="/Cake/assets/css/style.css">';

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
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
    }

    .product-content {
        padding: 18px;
        border-radius: 0;
    }

    .product-card img {
        height: 180px;
    }
}

@media (max-width: 520px) {
    .product-menu {
        padding: 16px;
    }

    .product-menu button {
        font-size: 14px;
        padding: 10px 12px;
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
    height: 300px;
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
    margin-top: auto;
    background: #4a1d1f;
    color: #fbedcd;
    border: none;
    padding: 11px;
    border-radius: 0;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

.add-btn:hover {
    background: #2f1415;
    box-shadow: 0 12px 20px rgba(74, 29, 31, 0.25);
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
        <?php foreach ($san_pham as $k => $ds): ?>
            <div id="<?= $k ?>" class="cat <?= $k == $loai_active ? '' : 'hidden' ?>">
                <h2 style="color:#4a1d1f; margin-bottom:18px;"><?= $ten_loai[$k] ?></h2>
                <div class="product-grid">
                    <?php if (!$ds): ?>
                        <p style="color:#888">Không có sản phẩm nào.</p>
                    <?php endif; ?>
                    <?php foreach ($ds as $p): ?>
                        <div class="product-card">
                            <?php $slug = !empty($p['slug']) ? $p['slug'] : slugify($p['ten_banh'], (int) $p['id']); ?>
                            <a class="product-link" href="/Cake/product/<?= urlencode($slug) ?>">
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
                            <button class="add-btn"
                                    onclick="addCartQuick(<?= $p['id'] ?>)">
                                <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ
                            </button>
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
    fetch('/Cake/pages/cart.php', {
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