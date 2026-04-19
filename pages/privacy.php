<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
<?php
$pageTitle = 'Chính sách bảo mật';
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
    transparent 8px,
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
.bg-green { background: #10b981; }
.bg-red { background: #ef4444; }

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
  color: #1f2937; 
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
      <i class="fa-solid fa-user-shield"></i> CHÍNH SÁCH BẢO MẬT
    </h1>

    <p class="text-center text-muted mb-4">
      Gấu Bakery tôn trọng quyền riêng tư và cam kết bảo vệ
      mọi thông tin cá nhân của khách hàng khi sử dụng dịch vụ.
    </p>

    <!-- NOTEBOOK -->
    <div class="notebook">
      <div class="accordion" id="privacyNotebook">

        <!-- A -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#privacyA">
              <span class="page-icon bg-blue">
                <i class="fa-solid fa-database"></i>
              </span>
              Thông tin chúng tôi thu thập
            </button>
          </h2>

          <div id="privacyA" class="accordion-collapse collapse show"
               data-bs-parent="#privacyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-primary">
                    <i class="fa-solid fa-user"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Thông tin cá nhân</strong>
                    <p>
                      Bao gồm họ tên, số điện thoại, email và địa chỉ giao hàng
                      do khách hàng cung cấp khi đăng ký tài khoản hoặc đặt hàng.
                    </p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-credit-card"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Thông tin thanh toán</strong>
                    <p>
                      Thông tin thanh toán chỉ được sử dụng để xử lý giao dịch
                      và không được lưu trữ trái phép.
                    </p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- B -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#privacyB">
              <span class="page-icon bg-green">
                <i class="fa-solid fa-lock"></i>
              </span>
              Cách chúng tôi bảo vệ thông tin
            </button>
          </h2>

          <div id="privacyB" class="accordion-collapse collapse"
               data-bs-parent="#privacyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-success">
                    <i class="fa-solid fa-shield-halved"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Công nghệ bảo mật</strong>
                    <p>
                      Áp dụng mã hóa dữ liệu, tường lửa và các biện pháp bảo mật
                      hiện đại để bảo vệ thông tin.
                    </p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-secondary">
                    <i class="fa-solid fa-user-lock"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Kiểm soát truy cập</strong>
                    <p>
                      Chỉ nhân sự được ủy quyền mới có quyền tiếp cận
                      thông tin cá nhân của khách hàng.
                    </p>
                  </div>
                </li>

              </ul>
            </div>
          </div>
        </div>

        <!-- C -->
        <div class="accordion-item notebook-page">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#privacyC">
              <span class="page-icon bg-red">
                <i class="fa-solid fa-share-nodes"></i>
              </span>
              Chia sẻ thông tin
            </button>
          </h2>

          <div id="privacyC" class="accordion-collapse collapse"
               data-bs-parent="#privacyNotebook">
            <div class="accordion-body">
              <ul class="policy-list">

                <li>
                  <span class="policy-item-icon text-danger">
                    <i class="fa-solid fa-ban"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Không chia sẻ trái phép</strong>
                    <p>
                      Gấu Bakery cam kết không mua bán, trao đổi
                      thông tin cá nhân khi chưa có sự đồng ý của khách hàng.
                    </p>
                  </div>
                </li>

                <li>
                  <span class="policy-item-icon text-warning">
                    <i class="fa-solid fa-scale-balanced"></i>
                  </span>
                  <div class="policy-item-content">
                    <strong>Tuân thủ pháp luật</strong>
                    <p>
                      Thông tin chỉ được cung cấp cho cơ quan chức năng
                      khi có yêu cầu hợp pháp.
                    </p>
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
