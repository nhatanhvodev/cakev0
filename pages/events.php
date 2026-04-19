<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

/* ================== KẾT NỐI DB (BẮT BUỘC PHẢI Ở TRÊN) ================== */
require_once '../config/connect.php';   // file này phải tạo $conn
$conn->set_charset("utf8mb4");

/* ================== THÊM VÀO GIỎ (DATABASE) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $banh_id = (int)($_POST['id'] ?? 0);
    $qty     = (int)($_POST['qty'] ?? 1);

    if ($banh_id > 0 && $qty > 0) {

        $check = $conn->prepare("
            SELECT id FROM cart 
            WHERE user_id = ? AND banh_id = ?
        ");
        $check->bind_param("ii", $user_id, $banh_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $update = $conn->prepare("
                UPDATE cart 
                SET quantity = quantity + ?
                WHERE user_id = ? AND banh_id = ?
            ");
            $update->bind_param("iii", $qty, $user_id, $banh_id);
            $update->execute();
        } else {
            $insert = $conn->prepare("
                INSERT INTO cart (user_id, banh_id, quantity)
                VALUES (?, ?, ?)
            ");
            $insert->bind_param("iii", $user_id, $banh_id, $qty);
            $insert->execute();
        }
    }

    header("Location: events.php");
    exit;
}


/* ================== LẤY KHUYẾN MÃI ================== */
$sql = "
  SELECT p.*, b.ten_banh, b.hinh_anh, b.gia AS gia_goc
  FROM promotions p
  JOIN banh b ON p.banh_id = b.id
  WHERE p.ngay_bat_dau <= CURDATE()
    AND p.ngay_ket_thuc >= CURDATE()
";
$promotions = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

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

/* ================== TRẠNG THÁI LOGIN ================== */
$isLoggedIn   = isset($_SESSION['user_id']);
$loggedInUser = $_SESSION['username'] ?? null;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
<meta charset="UTF-8">
<title>Sự kiện & Khuyến mãi | Gấu Bakery</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* =================================================================================
   EVENT / PROMOTION – STYLE ĐỒNG BỘ TOÀN SITE
   ================================================================================= */

/* 1. Background Pattern */
body{
  font-family:'Poppins',sans-serif;
  background-color:#e8f5f1;
  background-image:
    radial-gradient(circle at 10% 15%, rgba(255,255,255,.6) 0 40px, transparent 41px),
    radial-gradient(circle at 80% 20%, rgba(255,255,255,.5) 0 35px, transparent 36px),
    radial-gradient(circle at 30% 80%, rgba(255,255,255,.5) 0 45px, transparent 46px),
    url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'160\' height=\'160\' opacity=\'0.15\'><text x=\'10\' y=\'40\' font-size=\'28\'>🍰</text><text x=\'90\' y=\'60\' font-size=\'26\'>🍩</text><text x=\'40\' y=\'110\' font-size=\'26\'>🍬</text><text x=\'100\' y=\'120\' font-size=\'26\'>🍓</text></svg>");
  background-repeat:repeat;
  background-size:auto,auto,auto,220px 220px;
}

.container{
  max-width:1200px;
  margin:50px auto 40px;
}

/* 2. Hero Event */
.event-hero{
  background:#fff;
  border-radius:22px;
  padding:40px;
  margin-bottom:40px;
  border:2px solid #d9efe7;
  box-shadow:0 12px 30px rgba(69,119,98,.12);
  position:relative;
  overflow:hidden;
}

.event-hero::before{
  content:"<i class="fa-solid fa-champagne-glasses" style="color: #ffb703;"></i>";
  position:absolute;
  top:-22px;
  left:28px;
  width:50px;
  height:50px;
  background:linear-gradient(135deg,#ffb6c1,#ffd6dc);
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:22px;
  box-shadow:0 6px 18px rgba(0,0,0,.2);
}

.event-hero::after{
  content:"<i class="fa-solid fa-cake-candles" style="color: #ff6b9c;"></i> <i class="fa-solid fa-circle-notch" style="color: #ff6b9c;"></i> <i class="fa-brands fa-raspberry-pi" style="color: #d32f2f;"></i> <i class="fa-solid fa-candy-cane" style="color: #ff6b9c;"></i>";
  position:absolute;
  bottom:-12px;
  right:-12px;
  font-size:46px;
  opacity:.08;
  transform:rotate(-10deg);
}

.event-hero h1{
  color:#457762;
  font-size:32px;
  margin-bottom:12px;
}
.event-hero p{
  font-size:16px;
  max-width:720px;
  color:#355e4f;
}

/* 3. Product Grid */
.promo-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
  gap:26px;
}

/* 4. Product Card */
.product-card{
  background:#fff;
  border-radius:20px;
  padding:16px;
  border:2px solid #d9efe7;
  box-shadow:0 10px 26px rgba(69,119,98,.12);
  display:flex;
  flex-direction:column;
  transition:.25s ease;
  position:relative;
  overflow:hidden;
}

.product-card::after{
  content:"<i class="fa-solid fa-cake-candles" style="color: #ff6b9c;"></i>";
  position:absolute;
  top:10px;
  right:14px;
  font-size:26px;
  opacity:.15;
}

.product-card:hover{
  transform:translateY(-6px);
  box-shadow:0 16px 34px rgba(69,119,98,.18);
}

.product-card img{
  width:100%;
  height:160px;
  object-fit:cover;
  border-radius:14px;
  box-shadow:0 6px 14px rgba(0,0,0,.08);
}

.product-name{
  font-weight:600;
  margin:12px 0 6px;
  min-height:42px;
  color:#2f6f55;
}

.price del{
  color:#999;
  font-size:14px;
}
.price span{
  display:block;
  color:#e91e63;
  font-size:18px;
  font-weight:600;
}

/* 5. Button */
.add-to-cart{
  margin-top:auto;
  background:linear-gradient(135deg,#457762,#5fae92);
  color:#fff;
  border:none;
  padding:10px;
  border-radius:14px;
  cursor:pointer;
  font-weight:600;
}
.add-to-cart:hover{
  background:linear-gradient(135deg,#ff6b9c,#ff8fb3);
}

/* 6. Modal */
.quantity-modal{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.5);
  display:none;
  justify-content:center;
  align-items:center;
  z-index:1000;
}

.quantity-box{
  background:#fff;
  padding:26px;
  border-radius:20px;
  width:90%;
  max-width:420px;
  text-align:center;
  border:2px solid #d9efe7;
  box-shadow:0 12px 30px rgba(69,119,98,.2);
  position:relative;
}

.quantity-box h3{
  color:#457762;
  margin-bottom:16px;
}

.qty-control{
  display:flex;
  justify-content:center;
  gap:14px;
  margin-bottom:22px;
}

.qty-control button{
  width:38px;
  height:38px;
  border-radius:50%;
  border:none;
  background:#457762;
  color:#fff;
  font-size:18px;
}

.qty-control input{
  width:64px;
  text-align:center;
  border-radius:10px;
  border:1.5px solid #cfe7de;
}

.confirm-btn{
  background:linear-gradient(135deg,#457762,#5fae92);
  color:#fff;
  padding:12px 26px;
  border-radius:16px;
  border:none;
  font-weight:600;
}

.close-modal{
  position:absolute;
  top:14px;
  right:18px;
  font-size:22px;
  cursor:pointer;
}

@media (max-width: 900px){
  .container{margin:24px auto 24px;padding:0 16px;}
  .event-hero{padding:24px;}
  .event-hero h1{font-size:24px;}
  .promo-grid{gap:18px;}
  .product-card img{height:140px;}
}

@media (max-width: 520px){
  .event-hero{padding:20px;}
  .quantity-box{padding:20px;}
}

</style>
</head>

<body>

<?php include '../includes/header.php'; ?>

<div class="container">

  <!-- HERO -->
  <section class="event-hero">
    <h1><i class="fa-solid fa-champagne-glasses" style="color: #ffb703;"></i> Sự kiện khuyến mãi đặc biệt</h1>
    <p>
      Từ <strong>01/05 – 10/05</strong>, Gấu Bakery dành tặng ưu đãi
      <strong>giảm giá lên đến 50%</strong> cho các dòng bánh được yêu thích.
      Đừng bỏ lỡ cơ hội ngọt ngào này!
    </p>
  </section>

  <section class="promo-grid">
<?php foreach ($promotions as $p): ?>
  <div class="product-card">

    <img src="<?= htmlspecialchars(buildImageUrl($p['hinh_anh'])) ?>">
    <div class="product-name"><?= htmlspecialchars($p['ten_banh']) ?></div>

    <div class="price">
      <del><?= number_format($p['gia_goc'],0,',','.') ?> VNĐ</del>
      <span><?= number_format($p['gia_khuyen_mai'],0,',','.') ?> VNĐ</span>
    </div>

    <!-- FORM THÊM GIỎ HÀNG -->
    <form class="add-cart-form" method="POST" action="events.php">
      <input type="hidden" name="add_to_cart" value="1">
      <input type="hidden" name="id" value="<?= $p['banh_id'] ?>">
      <input type="hidden" name="name" value="<?= htmlspecialchars($p['ten_banh']) ?>">
      <input type="hidden" name="price" value="<?= $p['gia_khuyen_mai'] ?>">
      <input type="hidden" name="image" value="<?= htmlspecialchars($p['hinh_anh']) ?>">
      <input type="hidden" name="qty" value="1">

      <button type="button" class="add-to-cart">
        <i class="fa-solid fa-cart-shopping" style="color: #ff6b9c;"></i> Thêm vào giỏ
      </button>
    </form>

  </div>
<?php endforeach; ?>
</section>


<!-- MODAL -->
<div class="quantity-modal" id="qtyModal">
  <div class="quantity-box">
    <span class="close-modal">×</span>
    <h3 id="modalTitle"></h3>
    <div class="qty-control">
       <button id="minus" type="button">−</button>

      <input type="number" id="qtyInput" value="1" min="1">
      <button id="plus" type="button">+</button>

    </div>
    <button class="confirm-btn" id="confirmAdd">Xác nhận</button>
  </div>
</div>
<div style="height:140px"></div>
<?php include '../includes/footer.html'; ?>

<script>
let currentForm = null;
const modal = document.getElementById('qtyModal');
const qtyInput = document.getElementById('qtyInput');

document.querySelectorAll('.add-cart-form .add-to-cart').forEach(btn => {
  btn.onclick = () => {
    currentForm = btn.closest('form');
    const name = currentForm.querySelector('[name="name"]').value;
    document.getElementById('modalTitle').innerText =
      'Chọn số lượng cho ' + name;
    qtyInput.value = 1;
    modal.style.display = 'flex';
  };
});

document.getElementById('plus').onclick = () => {
  qtyInput.value = parseInt(qtyInput.value) + 1;
};

document.getElementById('minus').onclick = () => {
  if (qtyInput.value > 1) qtyInput.value--;
};

document.getElementById('confirmAdd').onclick = () => {
  if (!currentForm) return;
  currentForm.querySelector('[name="qty"]').value = qtyInput.value;
  modal.style.display = 'none';
  currentForm.submit(); 
};

document.querySelector('.close-modal').onclick = () => {
  modal.style.display = 'none';
};
</script>



</body>
</html>

<?php $conn->close(); ?>
