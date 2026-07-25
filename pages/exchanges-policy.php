<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$pageTitle = 'Chính sách đổi trả';
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
          <li aria-current="page">Chính sách đổi trả</li>
        </ol>
      </nav>

      <header class="policy-hero">
        <div class="policy-hero__inner">
          <div>
            <p class="policy-eyebrow">Tóm tắt chính sách</p>
            <h1>Chính sách đổi trả sản phẩm</h1>
            <p class="policy-lead">
              Gấu Bakery cam kết mang đến sản phẩm chất lượng và trải nghiệm mua sắm tốt nhất.
              Trong trường hợp phát sinh sự cố, vui lòng tham khảo chính sách dưới đây.
            </p>
          </div>
          <div class="policy-hero__icon" aria-hidden="true">
            <i class="fa-solid fa-rotate-left"></i>
          </div>
        </div>
      </header>

      <div class="policy-layout">
        <aside class="policy-toc" aria-labelledby="return-toc-title">
          <div class="policy-toc__card">
            <h2 id="return-toc-title">Trong trang này</h2>
            <ol>
              <li><a href="#dieu-kien">Điều kiện đổi trả</a></li>
              <li><a href="#chap-nhan">Trường hợp chấp nhận</a></li>
              <li><a href="#hinh-thuc">Hình thức đổi trả / hoàn tiền</a></li>
            </ol>
          </div>
        </aside>

        <article class="policy-content" aria-label="Nội dung chính sách đổi trả sản phẩm">
          <section id="dieu-kien" class="policy-section" aria-labelledby="dieu-kien-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i></span>
              <h2 id="dieu-kien-title">Điều kiện đổi trả</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
                <div>
                  <strong>Hóa đơn &amp; nhãn mác</strong>
                  <p>Cung cấp đầy đủ hóa đơn, tem nhãn còn nguyên vẹn.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                <div>
                  <strong>Hình ảnh / video</strong>
                  <p>Gửi hình ảnh hoặc video rõ nét thể hiện tình trạng sản phẩm.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
                <div>
                  <strong>Thời gian yêu cầu</strong>
                  <p>Yêu cầu đổi trả trong vòng 24 giờ kể từ khi nhận hàng.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="chap-nhan" class="policy-section" aria-labelledby="chap-nhan-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
              <h2 id="chap-nhan-title">Trường hợp chấp nhận đổi trả / hoàn tiền</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-truck-fast"></i></span>
                <div>
                  <strong>Hư hỏng khi vận chuyển</strong>
                  <p>Sản phẩm bị móp méo, vỡ nát trong quá trình giao hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-bug"></i></span>
                <div>
                  <strong>Dị vật / mùi lạ</strong>
                  <p>Phát hiện mùi lạ, ôi thiu hoặc dị vật trong sản phẩm.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-calendar-xmark"></i></span>
                <div>
                  <strong>Hết hạn sử dụng</strong>
                  <p>Sản phẩm hết hạn hoặc không đúng cam kết khi giao.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="hinh-thuc" class="policy-section" aria-labelledby="hinh-thuc-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
              <h2 id="hinh-thuc-title">Hình thức đổi trả / hoàn tiền</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-repeat"></i></span>
                <div>
                  <strong>Đổi sản phẩm mới</strong>
                  <p>Đổi sản phẩm mới có giá trị tương đương.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-money-bill-transfer"></i></span>
                <div>
                  <strong>Hoàn tiền 100%</strong>
                  <p>Hoàn tiền qua chuyển khoản trong 3–5 ngày làm việc.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>
                <div>
                  <strong>Miễn phí vận chuyển</strong>
                  <p>Gấu Bakery chịu toàn bộ phí vận chuyển đổi trả.</p>
                </div>
              </li>
            </ul>
          </section>

          <div class="policy-closing" role="note">
            <span class="policy-closing__icon" aria-hidden="true"><i class="fa-solid fa-hands-praying"></i></span>
            <p>Cảm ơn Quý khách đã tin tưởng <strong>Gấu Bakery</strong>.</p>
          </div>
        </article>
      </div>
    </div>
  </main>

  <?php include '../includes/footer.html'; ?>
</body>
</html>
