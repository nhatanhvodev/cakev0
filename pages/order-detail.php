<?php
session_start();
require_once '../config/connect.php';
if ($conn->connect_error) die("Lỗi DB");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ===== LẤY ĐƠN HÀNG ===== */
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die("Không có quyền xem đơn này");

function imgPath($path) {
    if (!$path) return '/Cake/assets/img/no-image.jpg';
    if (strpos($path, 'admin/img/') === 0 || strpos($path, 'admin/') === 0) {
        return '/Cake/' . ltrim($path, '/');
    }
    if (strpos($path, 'assets/') === false && strpos($path, 'img/') === 0) {
        $path = str_replace('img/', 'assets/img/', $path);
    }
    return '/Cake/' . ltrim($path, '/');
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

/* ===== TRẠNG THÁI ===== */
$status = strtolower($order['status']);
$statusClass = match ($status) {
    'completed', 'thanh cong' => 'success',
    'paid' => 'primary',
    'approved', 'confirmed' => 'info',
    'delivering' => 'info',
    'delivered', 'da giao' => 'info',
    'failed' => 'danger',
    'cancelled', 'huy' => 'danger',
    default => 'warning'
};

$statusLabel = match ($status) {
    'completed', 'thanh cong' => 'Hoàn tất',
    'paid' => 'Đã thanh toán',
    'approved', 'confirmed' => 'Đã xác nhận',
    'delivering' => 'Đang giao hàng',
    'delivered', 'da giao' => 'Đã giao hàng',
    'failed' => 'Thất bại',
    'cancelled', 'huy' => 'Đã hủy',
    'pending', 'cho xac nhan' => 'Chờ xác nhận',
    default => ucfirst($order['status'])
};

$allowedReviewStatuses = ['completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product_review'])) {
    if (!in_array($status, $allowedReviewStatuses, true)) {
        $_SESSION['review_flash'] = 'Đơn hàng chưa đủ điều kiện để đánh giá.';
        header("Location: order-detail.php?id={$order_id}#reviews");
        exit;
    }

    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
    $content = trim($_POST['content'] ?? '');

    if ($productId <= 0 || $rating < 1 || $rating > 5) {
        $_SESSION['review_flash'] = 'Vui lòng chọn số sao đánh giá.';
        header("Location: order-detail.php?id={$order_id}#reviews");
        exit;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM order_items WHERE order_id = ? AND banh_id = ?");
    $stmt->bind_param('ii', $order_id, $productId);
    $stmt->execute();
    $exists = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if ($exists === 0) {
        $_SESSION['review_flash'] = 'Sản phẩm không hợp lệ trong đơn hàng.';
        header("Location: order-detail.php?id={$order_id}#reviews");
        exit;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM product_reviews WHERE order_id = ? AND product_id = ? AND user_id = ?");
    $stmt->bind_param('iii', $order_id, $productId, $user_id);
    $stmt->execute();
    $already = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    if ($already > 0) {
        $_SESSION['review_flash'] = 'Bạn đã đánh giá sản phẩm này.';
        header("Location: order-detail.php?id={$order_id}#reviews");
        exit;
    }

    $displayName = $_SESSION['username'] ?? $order['recipient_name'] ?? 'Khách hàng';
    $stmt = $conn->prepare(
        "INSERT INTO product_reviews (product_id, order_id, user_id, name, rating, content)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iiisis', $productId, $order_id, $user_id, $displayName, $rating, $content);
    $stmt->execute();
    $stmt->close();

    $_SESSION['review_flash'] = 'Cảm ơn bạn đã đánh giá!';
    header("Location: order-detail.php?id={$order_id}#reviews");
    exit;
}

/* ===== CHI TIẾT BÁNH ===== */
$stmt = $conn->prepare("
    SELECT b.id, b.ten_banh, b.hinh_anh, b.slug, oi.quantity, oi.price
    FROM order_items oi
    JOIN banh b ON oi.banh_id = b.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$reviewedProducts = [];
$stmt = $conn->prepare("SELECT product_id FROM product_reviews WHERE order_id = ? AND user_id = ?");
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$reviewRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
foreach ($reviewRows as $row) {
    $reviewedProducts[(int) $row['product_id']] = true;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="/Cake/assets/img/logo.png" type="image/png">
<meta charset="UTF-8">
<title>Chi tiết đơn hàng #<?= $order_id ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: radial-gradient(circle at 12% 18%, #fff3da 0%, transparent 45%),
        radial-gradient(circle at 90% 12%, #fde8c6 0%, transparent 40%),
        #ffffff;
    color: #272727;
}

.order-shell {
    max-width: 1180px;
    margin: 24px auto 60px;
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 22px;
}

.order-hero {
    background: linear-gradient(135deg, #fff7ea, #fdf1db);
    border: 1px solid #f3e0be;
    border-radius: 26px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 18px 40px rgba(74, 29, 31, 0.12);
}

.order-hero h1 {
    margin: 0;
    font-size: 22px;
    color: #4a1d1f;
}

.order-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.order-grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 22px;
}

.order-panel {
    background: #fff;
    border: 1px solid #f3e0be;
    border-radius: 22px;
    padding: 20px;
    box-shadow: 0 14px 30px rgba(74, 29, 31, 0.08);
}

.order-panel h4 {
    margin: 0 0 14px;
    color: #4a1d1f;
    font-size: 18px;
}

.order-list {
    display: grid;
    gap: 10px;
    color: #4a4a4a;
    font-size: 14px;
}

.order-list span {
    color: #6a2d22;
    font-weight: 600;
}

.order-map {
    width: 100%;
    height: 180px;
    border: 0;
    border-radius: 16px;
    margin-top: 12px;
}

.order-table {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid #f3e0be;
}

.order-table thead {
    background: #fff1d6;
    color: #4a1d1f;
}

.order-total {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #f3e0be;
    padding: 16px 20px;
    text-align: right;
    font-size: 18px;
    font-weight: 700;
    color: #4a1d1f;
    box-shadow: 0 10px 24px rgba(74, 29, 31, 0.08);
}

.order-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.btn-pill {
    border-radius: 999px;
    padding: 10px 16px;
    font-weight: 600;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal.show { display: flex; }

.modal-box {
    background: #fff;
    width: 90%;
    max-width: 420px;
    border-radius: 18px;
    padding: 20px;
    text-align: center;
    animation: zoomIn .3s ease;
}

@keyframes zoomIn {
    from { transform: scale(.7); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

@media (max-width: 992px) {
    .order-grid {
        grid-template-columns: 1fr;
    }
    .order-hero {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .order-shell {
        padding: 0 16px;
        margin: 16px auto 40px;
    }

    .order-hero {
        padding: 18px;
    }

    .order-actions {
        justify-content: flex-start;
    }

    .order-map {
        height: 160px;
    }

    .order-total {
        font-size: 16px;
    }
}

@media print {
    body { background: #fff !important; }
    .order-actions, .modal { display: none !important; }
    .order-shell { padding: 0; }
}
</style>
</head>

<body>

<?php include '../includes/header.php'; ?>

<div class="order-shell">
    <div class="order-hero">
        <div>
            <h1>Chi tiết đơn hàng #<?= $order_id ?></h1>
            <div class="order-meta">
                <span class="badge bg-<?= $statusClass ?>"><?= $statusLabel ?></span>
                <span class="badge bg-light text-dark"><?= htmlspecialchars($order['payment_method']) ?></span>
                <span class="text-muted"><?= date("d/m/Y H:i", strtotime($order['created_at'])) ?></span>
            </div>
        </div>
        <div class="order-actions">
            <button class="btn btn-outline-primary btn-pill" onclick="openBill()"><i class="fa-regular fa-eye"></i> Xem nhanh</button>
            <button class="btn btn-outline-success btn-pill" onclick="window.print()"><i class="fa-solid fa-print"></i> In</button>
            <button class="btn btn-outline-danger btn-pill" onclick="exportPDF()"><i class="fa-regular fa-file-lines"></i> PDF</button>
            <a href="/Cake/pages/account.php" class="btn btn-secondary btn-pill">Quay lại</a>
        </div>
    </div>

    <div class="order-grid">
        <div class="order-panel">
            <h4><i class="fa-regular fa-user"></i> Người nhận</h4>
            <div class="order-list">
                <div><span>Họ tên:</span> <?= htmlspecialchars($order['recipient_name']) ?></div>
                <div><span>SĐT:</span> <?= htmlspecialchars($order['phone']) ?></div>
                <div><span>Địa chỉ:</span> <?= htmlspecialchars($order['address']) ?></div>
            </div>
            <iframe
                class="order-map"
                loading="lazy"
                src="https://www.google.com/maps?q=<?= urlencode($order['address']) ?>&output=embed"></iframe>
        </div>
        <div class="order-panel">
            <h4><i class="fa-regular fa-clipboard"></i> Thông tin đơn</h4>
            <div class="order-list">
                <div><span>Phương thức:</span> <?= htmlspecialchars($order['payment_method']) ?></div>
                <div><span>Trạng thái:</span> <?= $statusLabel ?></div>
                <div><span>Mã đơn:</span> #<?= $order_id ?></div>
                <div><span>Ngày đặt:</span> <?= date("d/m/Y H:i", strtotime($order['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <div class="order-panel">
        <h4><i class="fa-solid fa-cake-candles"></i> Sản phẩm đã đặt</h4>
        <div class="table-responsive">
            <table class="table align-middle order-table">
                <thead>
                <tr>
                    <th>Tên bánh</th>
                    <th>Số lượng</th>
                    <th>Giá</th>
                    <th>Thành tiền</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['ten_banh']) ?></td>
                    <td><?= $it['quantity'] ?></td>
                    <td><?= number_format($it['price']) ?> đ</td>
                    <td class="fw-bold text-danger">
                        <?= number_format($it['price']*$it['quantity']) ?> đ
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="order-panel" id="reviews">
        <h4><i class="fa-regular fa-star"></i> Đánh giá sản phẩm</h4>
        <?php if (!empty($_SESSION['review_flash'])): ?>
            <div class="alert alert-info"><?= htmlspecialchars($_SESSION['review_flash']) ?></div>
            <?php unset($_SESSION['review_flash']); ?>
        <?php endif; ?>

        <?php if (!in_array($status, $allowedReviewStatuses, true)): ?>
            <p>Đơn hàng chỉ có thể đánh giá khi đã hoàn tất.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle order-table">
                    <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đánh giá</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($items as $it):
                        $productSlug = !empty($it['slug']) ? $it['slug'] : slugify($it['ten_banh'], (int) $it['id']);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= imgPath($it['hinh_anh']) ?>" alt="<?= htmlspecialchars($it['ten_banh']) ?>" width="50" height="50" style="object-fit:cover;border-radius:10px;">
                                    <a href="/Cake/product/<?= urlencode($productSlug) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($it['ten_banh']) ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($reviewedProducts[(int) $it['id']])): ?>
                                    <span class="badge bg-success">Đã đánh giá</span>
                                <?php else: ?>
                                    <form method="POST" class="d-flex flex-column gap-2">
                                        <input type="hidden" name="submit_product_review" value="1">
                                        <input type="hidden" name="product_id" value="<?= (int) $it['id'] ?>">
                                        <div class="d-flex gap-2">
                                            <select name="rating" class="form-select form-select-sm" style="max-width: 120px;" required>
                                                <option value="">Sao</option>
                                                <option value="5">5</option>
                                                <option value="4">4</option>
                                                <option value="3">3</option>
                                                <option value="2">2</option>
                                                <option value="1">1</option>
                                            </select>
                                            <button class="btn btn-sm btn-success" type="submit">Gửi</button>
                                        </div>
                                        <textarea name="content" class="form-control form-control-sm" rows="2" placeholder="Chia sẻ cảm nhận (không bắt buộc)"></textarea>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="order-total">
        Tổng tiền: <span class="text-success"><?= number_format($order['total_amount']) ?> đ</span>
    </div>
</div>

<!-- MODAL -->
<div id="billModal" class="modal">
<div class="modal-box">
    <h5><i class="fa-solid fa-receipt" style="color: #8b4513;"></i> Hóa đơn #<?= $order_id ?></h5>
    <p><?= htmlspecialchars($order['recipient_name']) ?></p>
    <hr>
    <?php foreach($items as $it): ?>
        <p><?= $it['ten_banh'] ?> × <?= $it['quantity'] ?>
        = <strong><?= number_format($it['price']*$it['quantity']) ?> đ</strong></p>
    <?php endforeach; ?>
    <hr>
    <h6>Tổng: <?= number_format($order['total_amount']) ?> đ</h6>
    <button class="btn btn-secondary btn-sm mt-3" onclick="closeBill()">Đóng</button>
</div>
</div>

<?php include '../includes/footer.html'; ?>

<script>
function openBill(){ document.getElementById('billModal').classList.add('show'); }
function closeBill(){ document.getElementById('billModal').classList.remove('show'); }

function exportPDF(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Hoa don #<?= $order_id ?>",20,20);
    doc.text("Nguoi nhan: <?= $order['recipient_name'] ?>",20,30);
    let y=45;
    <?php foreach($items as $it): ?>
        doc.text("<?= $it['ten_banh'] ?> x <?= $it['quantity'] ?>",20,y);
        doc.text("<?= number_format($it['price']*$it['quantity']) ?> đ",150,y);
        y+=10;
    <?php endforeach; ?>
    doc.text("Tong tien: <?= number_format($order['total_amount']) ?> đ",20,y+10);
    doc.save("hoa-don-<?= $order_id ?>.pdf");
}
</script>

</body>
</html>
