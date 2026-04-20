<?php
session_start();
ob_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once '../config/connect.php';

$pageTitle = 'Sản phẩm yêu thích';
$today = date('Y-m-d');
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? (int) $_SESSION['user_id'] : 0;

function ensureFavoritesTable(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS favorites (
      id INT(11) NOT NULL AUTO_INCREMENT,
      user_id INT(11) NOT NULL,
      banh_id INT(11) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_favorites_user_product (user_id, banh_id),
      KEY idx_favorites_user (user_id),
      KEY idx_favorites_banh (banh_id),
      CONSTRAINT favorites_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT favorites_ibfk_2 FOREIGN KEY (banh_id) REFERENCES banh(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
}

function buildImageUrl(?string $path): string {
    $fallback = '/cakev0/assets/img/no-image.jpg';
    if (!$path) {
        return $fallback;
    }

    $path = trim((string) $path);
    if ($path === '') {
        return $fallback;
    }

    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:image/')) {
        return $path;
    }

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

ensureFavoritesTable($conn);

if (isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode([
            'success' => false,
            'require_login' => true,
            'message' => 'Vui lòng đăng nhập để lưu sản phẩm.'
        ]);
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $banhId = isset($_POST['banh_id']) ? (int) $_POST['banh_id'] : 0;

    if (!in_array($action, ['toggle', 'add', 'remove'], true)) {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
        exit;
    }

    if ($banhId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ.']);
        exit;
    }

    $exists = false;
    $stmtExists = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND banh_id = ? LIMIT 1");
    if ($stmtExists) {
        $stmtExists->bind_param('ii', $userId, $banhId);
        $stmtExists->execute();
        $exists = (bool) $stmtExists->get_result()->fetch_assoc();
        $stmtExists->close();
    }

    $isFavorite = $exists;
    $message = 'Đã cập nhật danh sách yêu thích.';

    if ($action === 'toggle') {
        if ($exists) {
            $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND banh_id = ?");
            if ($stmt) {
                $stmt->bind_param('ii', $userId, $banhId);
                $stmt->execute();
                $stmt->close();
            }
            $isFavorite = false;
            $message = 'Đã bỏ khỏi danh sách yêu thích.';
        } else {
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, banh_id) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ii', $userId, $banhId);
                $stmt->execute();
                $stmt->close();
            }
            $isFavorite = true;
            $message = 'Đã lưu vào danh sách yêu thích.';
        }
    }

    if ($action === 'add') {
        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, banh_id) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ii', $userId, $banhId);
                $stmt->execute();
                $stmt->close();
            }
        }
        $isFavorite = true;
        $message = 'Đã lưu vào danh sách yêu thích.';
    }

    if ($action === 'remove') {
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND banh_id = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $banhId);
            $stmt->execute();
            $stmt->close();
        }
        $isFavorite = false;
        $message = 'Đã bỏ khỏi danh sách yêu thích.';
    }

    $favoriteCount = 0;
    $stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM favorites WHERE user_id = ?");
    if ($stmtCount) {
        $stmtCount->bind_param('i', $userId);
        $stmtCount->execute();
        $row = $stmtCount->get_result()->fetch_assoc();
        $favoriteCount = (int) ($row['total'] ?? 0);
        $stmtCount->close();
    }

    echo json_encode([
        'success' => true,
        'is_favorite' => $isFavorite,
        'favorite_count' => $favoriteCount,
        'message' => $message
    ]);
    exit;
}

if (!$isLoggedIn) {
    $_SESSION['toast'] = [
        'msg' => 'Vui lòng đăng nhập để xem sản phẩm đã lưu.',
        'type' => 'warning'
    ];
    header('Location: /cakev0/pages/login.php');
    exit;
}

$favoriteItems = [];
$stmt = $conn->prepare(
    "SELECT f.created_at, b.id, b.ten_banh, b.slug, b.gia, b.hinh_anh, p.gia_khuyen_mai
     FROM favorites f
     JOIN banh b ON f.banh_id = b.id
     LEFT JOIN promotions p ON b.id = p.banh_id
     AND p.ngay_bat_dau <= ? AND p.ngay_ket_thuc >= ?
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
if ($stmt) {
    $stmt->bind_param('ssi', $today, $today, $userId);
    $stmt->execute();
    $favoriteItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$extraLinks = '<link rel="stylesheet" href="/cakev0/assets/css/style.css">';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Gấu Bakery</title>
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

.favorites-wrap {
    max-width: 1180px;
    margin: 26px auto 42px;
    padding: 0 24px;
}

.favorites-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.favorites-title {
    margin: 0;
    color: #4a1d1f;
    font-size: 30px;
}

.favorites-sub {
    margin: 6px 0 0;
    color: #6a6a6a;
}

.head-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 999px;
    border: 1px solid #4a1d1f;
    color: #4a1d1f;
    text-decoration: none;
    font-weight: 600;
}

.favorites-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.favorite-card {
    background: #fff;
    border: 1px solid #f3e0be;
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 16px 32px rgba(74, 29, 31, 0.08);
    display: flex;
    flex-direction: column;
}

.favorite-card a.card-link {
    text-decoration: none;
    color: inherit;
}

.favorite-card img {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    display: block;
}

.favorite-card-body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    height: 100%;
}

.favorite-name {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2d2d2d;
}

.favorite-price {
    margin: 0;
}

.favorite-price del {
    color: #adadad;
    margin-right: 6px;
}

.favorite-price .current {
    color: #4a1d1f;
    font-weight: 700;
    font-size: 17px;
}

.favorite-price .discount-rate {
    margin-left: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #b42318;
}

.favorite-actions {
    margin-top: auto;
    display: flex;
    gap: 8px;
}

.btn-primary-soft,
.btn-danger-soft {
    flex: 1;
    border: none;
    border-radius: 10px;
    padding: 10px 12px;
    font-weight: 600;
    cursor: pointer;
}

.btn-primary-soft {
    background: #4a1d1f;
    color: #fbedcd;
}

.btn-primary-soft:hover {
    background: #2f1415;
}

.btn-danger-soft {
    background: #fbe9e8;
    color: #a41818;
}

.btn-danger-soft:hover {
    background: #f8d8d6;
}

.empty-box {
    border: 1px dashed #e4cdaa;
    border-radius: 22px;
    padding: 34px 20px;
    text-align: center;
    color: #6f6f6f;
    background: #fff9f1;
}

.empty-box i {
    font-size: 44px;
    color: #d49958;
}

.empty-box h3 {
    margin: 10px 0 8px;
    color: #4a1d1f;
}

.empty-box a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    padding: 10px 16px;
    border-radius: 999px;
    border: 1px solid #4a1d1f;
    color: #4a1d1f;
    text-decoration: none;
    font-weight: 600;
}

.empty-box.is-hidden {
    display: none;
}

@media (max-width: 1024px) {
    .favorites-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .favorites-wrap {
        padding: 0 16px;
    }

    .favorites-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .favorites-grid {
        grid-template-columns: 1fr;
    }

    .favorite-card img {
        aspect-ratio: 1 / 1;
    }
}
</style>

<main class="page-content">
    <section class="favorites-wrap">
        <div class="favorites-head">
            <div>
                <h1 class="favorites-title">Sản phẩm đã lưu</h1>
                <p class="favorites-sub">Bạn đang lưu <?= count($favoriteItems) ?> sản phẩm yêu thích.</p>
            </div>
            <a href="/cakev0/pages/product.php" class="head-link">Tiếp tục mua sắm</a>
        </div>

        <div id="favoriteGrid" class="favorites-grid">
            <?php foreach ($favoriteItems as $item): ?>
                <?php
                    $slug = !empty($item['slug']) ? $item['slug'] : slugify($item['ten_banh'], (int) $item['id']);
                    $price = (float) ($item['gia_khuyen_mai'] ?: $item['gia']);
                    $discountPercent = null;
                    if (!empty($item['gia_khuyen_mai']) && (float) $item['gia'] > 0 && (float) $item['gia_khuyen_mai'] < (float) $item['gia']) {
                        $discountPercent = (int) round(100 - (((float) $item['gia_khuyen_mai'] / (float) $item['gia']) * 100));
                    }
                ?>
                <article class="favorite-card" data-product-id="<?= (int) $item['id'] ?>">
                    <a class="card-link" href="/cakev0/product/<?= urlencode($slug) ?>">
                        <img src="<?= buildImageUrl($item['hinh_anh']) ?>" alt="<?= htmlspecialchars($item['ten_banh']) ?>">
                    </a>
                    <div class="favorite-card-body">
                        <a class="card-link" href="/cakev0/product/<?= urlencode($slug) ?>">
                            <h3 class="favorite-name"><?= htmlspecialchars($item['ten_banh']) ?></h3>
                        </a>
                        <p class="favorite-price">
                            <?php if (!empty($item['gia_khuyen_mai'])): ?>
                                <del><?= number_format($item['gia'], 0, ',', '.') ?>đ</del>
                            <?php endif; ?>
                            <span class="current"><?= number_format($price, 0, ',', '.') ?>đ</span>
                            <?php if ($discountPercent !== null): ?>
                                <span class="discount-rate">-<?= $discountPercent ?>%</span>
                            <?php endif; ?>
                        </p>
                        <div class="favorite-actions">
                            <button type="button" class="btn-primary-soft" onclick="addCartQuick(<?= (int) $item['id'] ?>)">Thêm vào giỏ</button>
                            <button type="button" class="btn-danger-soft" data-product-id="<?= (int) $item['id'] ?>" onclick="removeFavorite(this)">Bỏ lưu</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div id="favoriteEmpty" class="empty-box <?= !empty($favoriteItems) ? 'is-hidden' : '' ?>">
            <i class="fa-regular fa-heart"></i>
            <h3>Bạn chưa lưu sản phẩm nào</h3>
            <p>Nhấn vào biểu tượng tim ở trang sản phẩm để lưu lại món bánh bạn thích.</p>
            <a href="/cakev0/pages/product.php">Khám phá sản phẩm</a>
        </div>
    </section>
</main>

<?php include '../includes/footer.html'; ?>

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
            window.showToast('Đã thêm vào giỏ hàng!', 'success');
            if (typeof d.cart_count !== 'undefined' && window.setCartBadge) {
                window.setCartBadge(d.cart_count);
            }
        } else {
            window.showToast('Không thêm được, vui lòng thử lại!', 'error');
        }
    })
    .catch(() => window.showToast('Lỗi kết nối máy chủ!', 'error'));
}

function removeFavorite(button) {
    const productId = parseInt(button.dataset.productId || '0', 10);
    if (!productId) return;

    fetch('/cakev0/pages/favorites.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=remove&banh_id=${productId}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            window.showToast(d.message || 'Không thể bỏ lưu sản phẩm.', 'error');
            if (d.require_login) {
                window.location.href = '/cakev0/pages/login.php';
            }
            return;
        }

        const card = button.closest('.favorite-card');
        if (card) {
            card.remove();
        }

        if (typeof d.favorite_count !== 'undefined' && window.setFavoriteBadge) {
            window.setFavoriteBadge(d.favorite_count);
        }

        window.showToast(d.message || 'Đã bỏ khỏi danh sách yêu thích.', 'success');
        refreshEmptyState();
    })
    .catch(() => window.showToast('Lỗi kết nối máy chủ!', 'error'));
}

function refreshEmptyState() {
    const grid = document.getElementById('favoriteGrid');
    const empty = document.getElementById('favoriteEmpty');
    const sub = document.querySelector('.favorites-sub');
    if (!grid || !empty || !sub) return;

    const count = grid.querySelectorAll('.favorite-card').length;
    sub.textContent = `Bạn đang lưu ${count} sản phẩm yêu thích.`;

    if (count === 0) {
        empty.classList.remove('is-hidden');
    }
}
</script>

<?php $conn->close(); ?>
</body>
</html>
