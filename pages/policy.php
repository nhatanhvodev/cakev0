<?php
$pageTitle = 'Chính sách đổi trả';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= !empty($pageTitle) ? htmlspecialchars($pageTitle) . ' | Gấu Bakery' : 'Gấu Bakery' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* ===== GLOBAL ===== */
body {
  background: #ffffff;
  color: #272727;
  font-family: 'Poppins', sans-serif;
}

.card {
  border: 1px solid #f3e0be !important;
  border-radius: 32px !important;
  box-shadow: 0 24px 48px rgba(74, 29, 31, 0.12) !important;
}

/* ===== NOTEBOOK ===== */
.notebook {
  background: linear-gradient(180deg, #fff7ea 0%, #fdf1db 100%);
  border-radius: 24px;
  padding: 28px;
  position: relative;
}

.notebook::before {
  content: "";
  position: absolute;
  left: 18px;
  top: 20px;
  bottom: 20px;
  width: 6px;
  background: repeating-linear-gradient(
    to bottom,
    #d6b892 8px,
    #fbedcd 8px,
    transparent 16px
  );
  border-radius: 6px;
}

/* ===== ACCORDION PAGE ===== */
.notebook-page {
  background: #fff;
  border-radius: 18px !important;
  margin-bottom: 18px;
  border: none;
  box-shadow: 0 8px 24px rgba(0,0,0,.08);
}

.accordion-button {
  border-radius: 18px !important;
  font-weight: 600;
  font-size: 17px;
  padding: 20px 24px;
  background: #f7efe1;
  color: #4a1d1f;
}

.accordion-button:not(.collapsed) {
  background: #f0e2c8;
  color: #4a1d1f;
}

.accordion-button::after {
  display: none;
}

/* ===== PAGE ICON ===== */
.page-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  margin-right: 14px;
  font-size: 18px;
}

.bg-blue { background: #3b82f6; }
.bg-red { background: #ef4444; }
.bg-green { background: #10b981; }

/* ===== POLICY LIST ===== */
.policy-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.policy-list li {
  display: flex;
  gap: 16px;
  background: #fdf7ef;
  padding: 18px 20px;
  border-radius: 16px;
  margin-bottom: 16px;
  border: 1px solid #f3e0be;
}

.policy-item-icon {
  font-size: 22px;
  min-width: 30px;
}

.policy-item-content strong {
  display: block;
  margin-bottom: 4px;
}

.policy-item-content p {
  margin: 0;
  color: #4a4a4a;
  font-size: 15px;
  line-height: 1.6;
}

@media (max-width: 768px) {
  .notebook { padding: 20px; }
  .accordion-button { font-size: 15px; padding: 14px 16px; }
  .policy-list li { flex-direction: column; align-items: flex-start; }
  .page-icon { margin-right: 0; margin-bottom: 8px; }
}

@media (max-width: 520px) {
  .card { border-radius: 24px !important; }
}
  </style>
</head>

<body>

<?php include '../includes/header.php'; ?>

<!-- ================= CONTENT ================= -->
<section class="container my-5">
  <div class="card shadow-lg border-0 rounded-4 p-4">

    <h1 class="text-center mb-4 fw-bold text-dark">
      <i class="fa-solid fa-rotate-left"></i> CHÍNH SÁCH ĐỔI TRẢ SẢN PHẨM
    </h1>

    <p class="text-center text-muted mb-4">
      Gấu Bakery cam kết mang đến sản phẩm chất lượng và trải nghiệm mua sắm tốt nhất.
      Trong trường hợp phát sinh sự cố, vui lòng tham khảo chính sách dưới đây.
    </p>

    <!-- NOTEBOOK -->
    <div class="notebook">
      <div class="accordion" id="returnNotebook">

        <!-- A -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#returnA">
              <span class="page-icon bg-blue">
                <i class="fa-solid fa-clipboard-check"></i>
              </span>
              Điều kiện đổi trả
            </button>
          </h2>

          <div id="returnA" class="accordion-collapse collapse show" data-bs-parent="#returnNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-receipt"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hóa đơn & nhãn mác</strong>
                    <p>Cung cấp đầy đủ hóa đơn, tem nhãn còn nguyên vẹn.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-info">
                    <i class="fa-solid fa-camera"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hình ảnh / video</strong>
                    <p>Gửi hình ảnh hoặc video rõ nét thể hiện tình trạng sản phẩm.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-warning">
                    <i class="fa-solid fa-clock"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Thời gian yêu cầu</strong>
                    <p>Yêu cầu đổi trả trong vòng 24 giờ kể từ khi nhận hàng.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- B -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#returnB">
              <span class="page-icon bg-red">
                <i class="fa-solid fa-triangle-exclamation"></i>
              </span>
              Trường hợp chấp nhận đổi trả / hoàn tiền
            </button>
          </h2>

          <div id="returnB" class="accordion-collapse collapse" data-bs-parent="#returnNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-danger">
                    <i class="fa-solid fa-truck-fast"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hư hỏng khi vận chuyển</strong>
                    <p>Sản phẩm bị móp méo, vỡ nát trong quá trình giao hàng.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-warning">
                    <i class="fa-solid fa-bug"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Dị vật / mùi lạ</strong>
                    <p>Phát hiện mùi lạ, ôi thiu hoặc dị vật trong sản phẩm.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-secondary">
                    <i class="fa-solid fa-calendar-xmark"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hết hạn sử dụng</strong>
                    <p>Sản phẩm hết hạn hoặc không đúng cam kết khi giao.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- C -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#returnC">
              <span class="page-icon bg-green">
                <i class="fa-solid fa-hand-holding-dollar"></i>
              </span>
              Hình thức đổi trả / hoàn tiền
            </button>
          </h2>

          <div id="returnC" class="accordion-collapse collapse" data-bs-parent="#returnNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-repeat"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Đổi sản phẩm mới</strong>
                    <p>Đổi sản phẩm mới có giá trị tương đương.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hoàn tiền 100%</strong>
                    <p>Hoàn tiền qua chuyển khoản trong 3–5 ngày làm việc.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-info">
                    <i class="fa-solid fa-truck"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Miễn phí vận chuyển</strong>
                    <p>Gấu Bakery chịu toàn bộ phí vận chuyển đổi trả.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>

    <p class="text-center fw-semibold mt-4">
      <i class="fa-solid fa-hands-praying" style="color: #8b4513;"></i> Cảm ơn Quý khách đã tin tưởng <strong>Gấu Bakery</strong>.
    </p>

  </div>
</section>

<?php include '../includes/footer.html'; ?>

</body>
</html>
