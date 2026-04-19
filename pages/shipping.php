<?php
$pageTitle = 'Chính sách vận chuyển';
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

.bg-pink   { background: #ec4899; }
.bg-yellow { background: #f59e0b; }
.bg-green  { background: #10b981; }
.bg-blue   { background: #3b82f6; }
.bg-red    { background: #ef4444; }

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

    <h1 class="text-center mb-4 fw-bold text-dark ">
      <i class="fa-solid fa-truck-fast"></i> CHÍNH SÁCH VẬN CHUYỂN
    </h1>

    <!-- NOTEBOOK -->
    <div class="notebook">
      <div class="accordion" id="policyNotebook">

        <!-- A -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#policyA">
              <span class="page-icon bg-pink">
                <i class="fa-solid fa-store"></i>
              </span>
              Phương thức giao hàng
            </button>
          </h2>

          <div id="policyA" class="accordion-collapse collapse show" data-bs-parent="#policyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-shop"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Mua trực tiếp tại cửa hàng</strong>
                    <p>Khách hàng đến trực tiếp cửa hàng để chọn bánh và nhận ngay.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-truck-fast"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Giao hàng tận nơi</strong>
                    <p>Giao bánh đến địa chỉ khách cung cấp, đảm bảo an toàn và đúng hẹn.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- B -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#policyB">
              <span class="page-icon bg-yellow">
                <i class="fa-solid fa-clock"></i>
              </span>
              Thời gian giao hàng
            </button>
          </h2>

          <div id="policyB" class="accordion-collapse collapse" data-bs-parent="#policyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-warning">
                    <i class="fa-solid fa-clock"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Thời gian linh hoạt</strong>
                    <p>Phụ thuộc loại bánh, địa điểm và thời điểm đặt hàng.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-coins"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Minh bạch chi phí</strong>
                    <p>Thông báo rõ phí vận chuyển trước khi giao hàng.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-danger">
                    <i class="fa-solid fa-ban"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Quyền hủy đơn</strong>
                    <p>Được hủy nếu giao trễ không do lỗi khách hàng.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-secondary">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Trường hợp chậm trễ</strong>
                    <p>Do địa chỉ sai, không liên lạc được hoặc sự cố vận chuyển.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- C -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#policyC">
              <span class="page-icon bg-green">
                <i class="fa-solid fa-map-location-dot"></i>
              </span>
              Phạm vi giao hàng
            </button>
          </h2>

          <div id="policyC" class="accordion-collapse collapse" data-bs-parent="#policyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-earth-asia"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Toàn quốc</strong>
                    <p>Hỗ trợ giao hàng trên toàn quốc với đơn sỉ, đơn lớn.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-handshake"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Đối tác uy tín</strong>
                    <p>Hợp tác với đơn vị vận chuyển chuyên nghiệp.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- D -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#policyD">
              <span class="page-icon bg-blue">
                <i class="fa-solid fa-file-invoice"></i>
              </span>
              Chứng từ hàng hóa
            </button>
          </h2>

          <div id="policyD" class="accordion-collapse collapse" data-bs-parent="#policyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-receipt"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hóa đơn đầy đủ</strong>
                    <p>Cung cấp hóa đơn theo đơn hàng hoặc theo yêu cầu.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-box"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Đóng gói cẩn thận</strong>
                    <p>Nguyên đai, nguyên kiện trước khi bàn giao.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-secondary">
                    <i class="fa-solid fa-id-card"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Thông tin rõ ràng</strong>
                    <p>Đầy đủ tên, số điện thoại, mã đơn hàng.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- E -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#policyE">
              <span class="page-icon bg-red">
                <i class="fa-solid fa-triangle-exclamation"></i>
              </span>
              Trách nhiệm khi hàng hư hỏng
            </button>
          </h2>

          <div id="policyE" class="accordion-collapse collapse" data-bs-parent="#policyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-danger">
                    <i class="fa-solid fa-xmark"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Từ chối nhận hàng</strong>
                    <p>Khách được quyền từ chối nếu hàng hư hỏng.</p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-warning">
                    <i class="fa-solid fa-rotate"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Hỗ trợ đổi/trả</strong>
                    <p>Đổi hoặc hoàn tiền theo chính sách đã cam kết.</p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>

<?php include '../includes/footer.html'; ?>

</body>
</html>
