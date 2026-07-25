<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$pageTitle = 'Chính sách vận chuyển';
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
          <li aria-current="page">Chính sách vận chuyển</li>
        </ol>
      </nav>

      <header class="policy-hero">
        <div class="policy-hero__inner">
          <div>
            <p class="policy-eyebrow">Tóm tắt chính sách</p>
            <h1>Chính sách vận chuyển</h1>
            <p class="policy-lead">
              Thông tin về phương thức, thời gian, phạm vi giao hàng, chứng từ
              và trách nhiệm khi sản phẩm hư hỏng.
            </p>
          </div>
          <div class="policy-hero__icon" aria-hidden="true">
            <i class="fa-solid fa-truck-fast"></i>
          </div>
        </div>
      </header>

      <div class="policy-layout">
        <aside class="policy-toc" aria-labelledby="shipping-toc-title">
          <div class="policy-toc__card">
            <h2 id="shipping-toc-title">Trong trang này</h2>
            <ol>
              <li><a href="#phuong-thuc">Phương thức giao hàng</a></li>
              <li><a href="#thoi-gian">Thời gian giao hàng</a></li>
              <li><a href="#pham-vi">Phạm vi giao hàng</a></li>
              <li><a href="#chung-tu">Chứng từ hàng hóa</a></li>
              <li><a href="#hu-hong">Trách nhiệm khi hàng hư hỏng</a></li>
            </ol>
          </div>
        </aside>

        <article class="policy-content" aria-label="Nội dung chính sách vận chuyển">
          <section id="phuong-thuc" class="policy-section" aria-labelledby="phuong-thuc-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-store"></i></span>
              <h2 id="phuong-thuc-title">Phương thức giao hàng</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-shop"></i></span>
                <div>
                  <strong>Mua trực tiếp tại cửa hàng</strong>
                  <p>Khách hàng đến trực tiếp cửa hàng để chọn bánh và nhận ngay.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-truck-fast"></i></span>
                <div>
                  <strong>Giao hàng tận nơi</strong>
                  <p>Giao bánh đến địa chỉ khách cung cấp, đảm bảo an toàn và đúng hẹn.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="thoi-gian" class="policy-section" aria-labelledby="thoi-gian-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
              <h2 id="thoi-gian-title">Thời gian giao hàng</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span>
                <div>
                  <strong>Thời gian linh hoạt</strong>
                  <p>Phụ thuộc loại bánh, địa điểm và thời điểm đặt hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-coins"></i></span>
                <div>
                  <strong>Minh bạch chi phí</strong>
                  <p>Thông báo rõ phí vận chuyển trước khi giao hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-ban"></i></span>
                <div>
                  <strong>Quyền hủy đơn</strong>
                  <p>Được hủy nếu giao trễ không do lỗi khách hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div>
                  <strong>Trường hợp chậm trễ</strong>
                  <p>Do địa chỉ sai, không liên lạc được hoặc sự cố vận chuyển.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="pham-vi" class="policy-section" aria-labelledby="pham-vi-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-map-location-dot"></i></span>
              <h2 id="pham-vi-title">Phạm vi giao hàng</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-earth-asia"></i></span>
                <div>
                  <strong>Toàn quốc</strong>
                  <p>Hỗ trợ giao hàng trên toàn quốc với đơn sỉ, đơn lớn.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-handshake"></i></span>
                <div>
                  <strong>Đối tác uy tín</strong>
                  <p>Hợp tác với đơn vị vận chuyển chuyên nghiệp.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="chung-tu" class="policy-section" aria-labelledby="chung-tu-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-file-invoice"></i></span>
              <h2 id="chung-tu-title">Chứng từ hàng hóa</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
                <div>
                  <strong>Hóa đơn đầy đủ</strong>
                  <p>Cung cấp hóa đơn theo đơn hàng hoặc theo yêu cầu.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                <div>
                  <strong>Đóng gói cẩn thận</strong>
                  <p>Nguyên đai, nguyên kiện trước khi bàn giao.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-id-card"></i></span>
                <div>
                  <strong>Thông tin rõ ràng</strong>
                  <p>Đầy đủ tên, số điện thoại, mã đơn hàng.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="hu-hong" class="policy-section" aria-labelledby="hu-hong-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
              <h2 id="hu-hong-title">Trách nhiệm khi hàng hư hỏng</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span>
                <div>
                  <strong>Từ chối nhận hàng</strong>
                  <p>Khách được quyền từ chối nếu hàng hư hỏng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-rotate"></i></span>
                <div>
                  <strong>Hỗ trợ đổi/trả</strong>
                  <p>Đổi hoặc hoàn tiền theo chính sách đã cam kết.</p>
                </div>
              </li>
            </ul>
          </section>
        </article>
      </div>
    </div>
  </main>

  <?php include '../includes/footer.html'; ?>
</body>
</html>
