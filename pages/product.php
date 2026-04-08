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
    'kem'  => 'Bánh kem'
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
    $params = [$today, $today];
    $types = 'ss';

    foreach ($normalizedTerms as $index => $term) {
        if (isset($categoryMap[$term])) {
            $whereParts[] = "b.loai = ?";
            $params[] = $categoryMap[$term];
            $types .= 's';
        }
        if (!empty($terms[$index])) {
            $whereParts[] = "b.ten_banh LIKE ?";
            $params[] = '%' . $terms[$index] . '%';
            $types .= 's';
        }
    }

    if (empty($whereParts)) {
        $whereParts[] = "b.ten_banh LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }

    $sql = "SELECT b.*, p.gia_khuyen_mai
            FROM banh b
            LEFT JOIN promotions p ON b.id=p.banh_id
            AND p.ngay_bat_dau<=? AND p.ngay_ket_thuc>=?
            WHERE " . implode(' OR ', $whereParts);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $san_pham['search'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ten_loai['search'] = "Kết quả: \"$search\"";
    $loai_active = 'search';
} else {
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
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 24px;
    padding: 30px 20px 40px;
    max-width: 1180px;
    margin: 20px auto 10px;
    align-items: start;
}

@media(max-width: 900px) {
    .products-wrap { grid-template-columns: 1fr; }
}

.product-menu {
    background: #fff;
    padding: 20px;
    border-radius: 24px;
    height: fit-content;
    border: 1px solid #f3e0be;
    box-shadow: 0 16px 32px rgba(74, 29, 31, 0.08);
    position: sticky;
    top: 110px;
}

.product-menu h3 {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #7c6b67;
    margin: 0 0 14px;
}

.product-menu button {
    width: 100%;
    padding: 12px 16px;
    border: none;
    border-radius: 12px;
    background: transparent;
    margin-bottom: 6px;
    text-align: left;
    font-weight: 600;
    font-size: 15px;
    color: #444;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-menu button.active,
.product-menu button:hover {
    background: #4a1d1f;
    color: #fbedcd;
    box-shadow: 0 10px 20px rgba(74, 29, 31, 0.2);
}

.product-content {
    background: #fff;
    border-radius: 28px;
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
    .product-grid { grid-template-columns: 1fr; }
}

.product-card {
    background: #fff;
    border-radius: 24px;
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
    height: 160px;
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
.price span { color: #4a1d1f; font-weight: 700; font-size: 16px; }

.add-btn {
    margin-top: auto;
    background: #4a1d1f;
    color: #fbedcd;
    border: none;
    padding: 11px;
    border-radius: 12px;
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

/* Modal */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 2000;
}

.modal-box {
    background: #fff;
    width: 90%;
    max-width: 400px;
    border-radius: 24px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}

.modal-box h3 {
    margin: 0 0 6px;
    font-size: 18px;
    color: #2f2f2f;
}

.modal-box .modal-price {
    color: #4a1d1f;
    font-weight: 700;
    font-size: 20px;
    margin-bottom: 20px;
}

.qty {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin: 0 0 22px;
}

.qty-btn {
    width: 38px;
    height: 38px;
    cursor: pointer;
    border: 2px solid #ddd;
    background: #f9f9f9;
    border-radius: 50%;
    font-size: 18px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.qty-btn:hover {
    border-color: #4a1d1f;
    background: #4a1d1f;
    color: #fff;
}

.qty input {
    width: 60px;
    text-align: center;
    padding: 8px;
    border: 2px solid #eee;
    border-radius: 10px;
    font-size: 18px;
    font-weight: 700;
}

.hidden { display: none; }
</style>

<main class="page-content">
<div class="products-wrap">
    <aside class="product-menu">
        <h3><i class="fa-solid fa-layer-group"></i> Danh mục</h3>
        <?php
        $menuIcons = [
            'ngot'   => 'fa-cookie-bite',
            'man'    => 'fa-bread-slice',
            'mi'     => 'fa-wheat-awn',
            'kem'    => 'fa-ice-cream',
            'search' => 'fa-magnifying-glass',
        ];
        foreach ($ten_loai as $k => $v): ?>
            <button class="<?= $k == $loai_active ? 'active' : '' ?>"
                    onclick="showCat('<?= $k ?>', this)">
                <i class="fa-solid <?= $menuIcons[$k] ?? 'fa-circle' ?>"></i>
                <?= $v ?>
            </button>
        <?php endforeach; ?>
    </aside>

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
                                    <span><?= number_format($p['gia_khuyen_mai']) ?>đ</span>
                                <?php else: ?>
                                    <span><?= number_format($p['gia']) ?>đ</span>
                                <?php endif; ?>
                            </div>
                            <button class="add-btn"
                                    onclick="openQty(<?= $p['id'] ?>,'<?= htmlspecialchars($p['ten_banh'], ENT_QUOTES) ?>',<?= $p['gia_khuyen_mai'] ?: $p['gia'] ?>,'<?= img($p['hinh_anh']) ?>')">
                                <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<!-- MODAL CHỌN SỐ LƯỢNG -->
<div id="qtyModal" class="modal">
    <div class="modal-box">
        <h3 id="mName"></h3>
        <div id="mPrice" class="modal-price"></div>
        <div class="qty">
            <button class="qty-btn" onclick="chg(-1)">−</button>
            <input id="mQty" type="number" value="1" min="1">
            <button class="qty-btn" onclick="chg(1)">+</button>
        </div>
        <button class="add-btn" onclick="addCart()" style="width:100%; justify-content:center;">
            <i class="fa-solid fa-check"></i> Xác nhận thêm
        </button>
    </div>
</div>
</main>

<?php include '../includes/footer.html'; ?>

<script>
let cur = {};
const qtyModal = document.getElementById('qtyModal');
const mQty = document.getElementById('mQty');

function showCat(id, btn) {
    document.querySelectorAll('.cat').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.product-menu button').forEach(b => b.classList.remove('active'));
    const target = document.getElementById(id);
    if (target) target.classList.remove('hidden');
    if (btn) btn.classList.add('active');
}

function openQty(id, name, price, imgUrl) {
    cur = { id, name, price, imgUrl };
    document.getElementById('mName').innerText = name;
    document.getElementById('mPrice').innerText = new Intl.NumberFormat('vi-VN').format(price) + 'đ';
    mQty.value = 1;
    qtyModal.style.display = 'flex';
}

function chg(v) {
    mQty.value = Math.max(1, parseInt(mQty.value) + v);
}

function addCart() {
    let q = parseInt(mQty.value);
    fetch('/Cake/pages/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&banh_id=${cur.id}&qty=${q}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.showToast('🧁 Đã thêm vào giỏ hàng!', 'success');
            qtyModal.style.display = 'none';
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

qtyModal.onclick = e => {
    if (e.target.id === 'qtyModal') qtyModal.style.display = 'none';
};
</script>

<?php $conn->close(); ?>