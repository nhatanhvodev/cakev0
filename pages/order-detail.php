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

/* ===== CHI TIẾT BÁNH ===== */
$stmt = $conn->prepare("
    SELECT b.ten_banh, oi.quantity, oi.price
    FROM order_items oi
    JOIN banh b ON oi.banh_id = b.id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
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
                <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($order['status']) ?></span>
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
                <div><span>Trạng thái:</span> <?= ucfirst($order['status']) ?></div>
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
