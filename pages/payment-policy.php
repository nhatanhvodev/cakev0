<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$pageTitle = 'Chính sách thanh toán';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Gấu Bakery</title>
  <link rel="icon" href="/cakev0/assets/img/logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/policy-pages.css">
</head>
<body class="policy-body">
  <a class="policy-skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
  <?php include '../includes/header.php'; ?>

  <main id="main-content" class="policy-page">
    <div class="policy-shell">
      <nav class="policy-breadcrumb" aria-label="Đường dẫn">
        <ol>
          <li><a href="../index.php">Trang chủ</a></li>
          <li aria-current="page">Chính sách thanh toán</li>
        </ol>
      </nav>

      <header class="policy-hero">
        <div class="policy-hero__inner">
          <div>
            <p class="policy-eyebrow">Tóm tắt chính sách</p>
            <h1>Chính sách thanh toán</h1>
            <p class="policy-lead">
              Các hình thức thanh toán được hỗ trợ, quy định xác nhận đơn hàng
              và cam kết bảo mật thông tin giao dịch.
            </p>
          </div>
          <div class="policy-hero__icon" aria-hidden="true">
            <i class="fa-solid fa-wallet"></i>
          </div>
        </div>
      </header>

      <div class="policy-layout">
        <aside class="policy-toc" aria-labelledby="payment-toc-title">
          <div class="policy-toc__card">
            <h2 id="payment-toc-title">Trong trang này</h2>
            <ol>
              <li><a href="#hinh-thuc">Hình thức thanh toán</a></li>
              <li><a href="#quy-dinh">Quy định thanh toán</a></li>
              <li><a href="#bao-mat">Bảo mật thanh toán</a></li>
            </ol>
          </div>
        </aside>

        <article class="policy-content" aria-label="Nội dung chính sách thanh toán">
          <section id="hinh-thuc" class="policy-section" aria-labelledby="hinh-thuc-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
              <h2 id="hinh-thuc-title">Hình thức thanh toán</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-money-bill-wave"></i></span>
                <div>
                  <strong>Thanh toán khi nhận hàng (COD)</strong>
                  <p>Thanh toán trực tiếp bằng tiền mặt cho nhân viên giao hàng khi nhận sản phẩm.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-qrcode"></i></span>
                <div>
                  <strong>Chuyển khoản QR (SePay)</strong>
                  <p>Quét mã VietQR, chuyển đúng nội dung đơn hàng và hệ thống tự xác nhận sau khi SePay ghi nhận giao dịch.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="quy-dinh" class="policy-section" aria-labelledby="quy-dinh-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></span>
              <h2 id="quy-dinh-title">Quy định thanh toán</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
                <div>
                  <strong>Xác nhận đơn hàng</strong>
                  <p>Đơn SePay sẽ được xử lý sau khi hệ thống xác nhận thanh toán thành công. Với COD, đơn được ghi nhận ngay sau khi xác nhận thông tin giao hàng.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="bao-mat" class="policy-section" aria-labelledby="bao-mat-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
              <h2 id="bao-mat-title">Bảo mật thanh toán</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                <div>
                  <strong>Bảo mật thông tin</strong>
                  <p>Mọi thông tin giao dịch và thanh toán của khách hàng đều được cam kết bảo mật tuyệt đối.</p>
                </div>
              </li>
            </ul>
          </section>

          <div class="policy-closing" role="note">
            <span class="policy-closing__icon" aria-hidden="true"><i class="fa-regular fa-credit-card"></i></span>
            <p>Cảm ơn Quý khách đã tin tưởng <strong>Gấu Bakery</strong></p>
          </div>
        </article>
      </div>
    </div>
  </main>

  <?php include '../includes/footer.html'; ?>
</body>
</html>
