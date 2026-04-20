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
    display: grid;
    gap: 28px;
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
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #e7d0b1;
    background: #fff7ed;
    color: #7a4725;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.wishlist-btn:hover {
    background: #fcebd5;
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
    height: 300px;
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
        flex-direction: column;
        align-items: stretch;
    }

    .qty-input {
        width: 100%;
    }

    .related-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="page-content">
<div class="detail-wrap">
    <section class="detail-hero">
        <div>
            <div class="detail-gallery-shell">
                <div class="detail-gallery">
                    <div class="detail-track">
                        <?php foreach ($galleryImages as $img): ?>
                            <img src="<?= imgPath($img['image_path']) ?>" alt="<?= htmlspecialchars($selected['ten_banh']) ?>">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (count($galleryImages) > 1): ?>
                    <button class="detail-nav-btn prev" type="button" aria-label="Ảnh trước">&lt;</button>
                    <button class="detail-nav-btn next" type="button" aria-label="Ảnh kế">&gt;</button>
                <?php endif; ?>
            </div>
            <?php if (count($galleryImages) > 1): ?>
                <div class="detail-dots">
                    <?php foreach ($galleryImages as $index => $img): ?>
                        <button class="detail-dot <?= $index === 0 ? 'active' : '' ?>" type="button" data-index="<?= $index ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="detail-info">
            <h1><?= htmlspecialchars($selected['ten_banh']) ?></h1>
            <div class="detail-price">
                <?php if (!empty($selected['gia_khuyen_mai'])): ?>
                    <del><?= number_format($selected['gia']) ?>đ</del>
                    <?= number_format($selected['gia_khuyen_mai']) ?>đ
                    <?php if ($selected['gia'] > 0): ?>
                        <?php $discount = (int) round(100 - (($selected['gia_khuyen_mai'] / $selected['gia']) * 100)); ?>
                        <span class="detail-discount">-<?= $discount ?>%</span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= number_format($selected['gia']) ?>đ
                <?php endif; ?>
            </div>
            <?php if (!empty($selected['mo_ta'])): ?>
                <?php
                $desc = html_entity_decode($selected['mo_ta'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $desc = strip_tags($desc, '<p><br><strong><b><em><i><u><ul><ol><li><a><span><h1><h2><h3><h4><h5><h6>');
                ?>
                <div class="detail-desc"><?= $desc ?></div>
            <?php else: ?>
                <p>Thông tin chi tiết sẽ được cập nhật sớm.</p>
            <?php endif; ?>
            <div class="detail-actions">
                <input class="qty-input" type="number" id="detailQty" value="1" min="1">
                <button class="cta-btn" onclick="addDetailToCart(<?= (int) $selected['id'] ?>, '<?= htmlspecialchars($selected['ten_banh'], ENT_QUOTES) ?>', <?= (float) ($selected['gia_khuyen_mai'] ?: $selected['gia']) ?>, '<?= imgPath($selected['hinh_anh']) ?>')">
                    Thêm vào giỏ
                </button>
                <button type="button"
                        class="wishlist-btn <?= $isFavorite ? 'is-active' : '' ?>"
                        id="detailFavoriteBtn"
                        data-product-id="<?= (int) $selected['id'] ?>"
                        onclick="toggleDetailFavorite(this)">
                    <i class="<?= $isFavorite ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                    <span><?= $isFavorite ? 'Đã lưu' : 'Lưu sản phẩm' ?></span>
                </button>
            </div>
        </div>
    </section>

    <section class="section-card">
        <h3 class="section-title">Đánh giá sản phẩm</h3>
        <div class="review-filters">
            <button class="review-filter-btn active" data-rating="all">Tất cả (<?= array_sum($reviewCounts) ?>)</button>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <button class="review-filter-btn" data-rating="<?= $i ?>"><?= $i ?> sao (<?= $reviewCounts[$i] ?>)</button>
            <?php endfor; ?>
        </div>
        <?php if (empty($reviews)): ?>
            <p>Chưa có đánh giá.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="review-item" data-rating="<?= (int) $review['rating'] ?>">
                    <div class="review-head">
                        <span class="review-name"><?= htmlspecialchars($review['name']) ?></span>
                        <span class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= $i <= (int) $review['rating'] ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                    <p><?= htmlspecialchars($review['content']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="section-card">
        <h3 class="section-title">Sản phẩm liên quan</h3>
        <?php if (empty($related)): ?>
            <p>Chưa có sản phẩm liên quan.</p>
        <?php else: ?>
            <div class="related-grid">
                <?php foreach ($related as $item):
                    $relatedSlug = !empty($item['slug']) ? $item['slug'] : slugify($item['ten_banh'], (int) $item['id']);
                ?>
                    <a class="related-card" href="/cakev0/product/<?= urlencode($relatedSlug) ?>">
                        <img src="<?= imgPath($item['hinh_anh']) ?>" alt="<?= htmlspecialchars($item['ten_banh']) ?>">
                        <div class="related-name"><?= htmlspecialchars($item['ten_banh']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
</main>

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
        dots.forEach((dot) => dot.classList.remove('active'));
        if (dots[index]) {
            dots[index].classList.add('active');
        }
        activeIndex = index;
        updateNavButtons();
    };

    const scrollToIndex = (index) => {
        const width = gallery.clientWidth || 1;
        gallery.scrollTo({ left: width * index, behavior: 'smooth' });
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
filterButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        const rating = btn.getAttribute('data-rating');
        filterButtons.forEach((item) => item.classList.remove('active'));
        btn.classList.add('active');
        reviewItems.forEach((item) => {
            if (rating === 'all' || item.getAttribute('data-rating') === rating) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

function addDetailToCart(id, name, price, imgUrl) {
    const qty = Math.max(1, parseInt(document.getElementById('detailQty').value || '1'));
    fetch('/cakev0/pages/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add&banh_id=${id}&qty=${qty}`
    })
    .then(r => r.json())
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
            window.showToast('Không thêm được, vui lòng thử lại!', 'error');
        }
    })
    .catch(() => {
        if (window.showToast) {
            window.showToast('Lỗi kết nối máy chủ!', 'error');
        }
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
</script>

<?php $conn->close(); ?>
</body>
</html>
