<?php
// Uses the shared responsive policy layout.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
$pageTitle = 'Chính sách bảo mật';
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
          <li aria-current="page">Chính sách bảo mật</li>
        </ol>
      </nav>

      <header class="policy-hero">
        <div class="policy-hero__inner">
          <div>
            <p class="policy-eyebrow">Tóm tắt chính sách</p>
            <h1>Chính sách bảo mật</h1>
            <p class="policy-lead">
              Gấu Bakery tôn trọng quyền riêng tư và cam kết bảo vệ mọi thông tin cá nhân
              của khách hàng khi sử dụng dịch vụ.
            </p>
            <ul class="policy-meta" aria-label="Thông tin tài liệu">
              <li><i class="fa-regular fa-calendar" aria-hidden="true"></i> Cập nhật: 25/07/2026</li>
              <li><i class="fa-regular fa-clock" aria-hidden="true"></i> Thời gian đọc: 2 phút</li>
            </ul>
          </div>
          <div class="policy-hero__icon" aria-hidden="true">
            <i class="fa-solid fa-user-shield"></i>
          </div>
        </div>
      </header>

      <div class="policy-layout">
        <aside class="policy-toc" aria-labelledby="privacy-toc-title">
          <div class="policy-toc__card">
            <h2 id="privacy-toc-title">Trong trang này</h2>
            <ol>
              <li><a href="#thu-thap">Thông tin chúng tôi thu thập</a></li>
              <li><a href="#bao-ve">Cách chúng tôi bảo vệ thông tin</a></li>
              <li><a href="#chia-se">Chia sẻ thông tin</a></li>
            </ol>
          </div>
        </aside>

        <article class="policy-content" aria-label="Nội dung chính sách bảo mật">
          <section id="thu-thap" class="policy-section" aria-labelledby="thu-thap-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-database"></i></span>
              <h2 id="thu-thap-title">Thông tin chúng tôi thu thập</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                <div>
                  <strong>Thông tin cá nhân</strong>
                  <p>Bao gồm họ tên, số điện thoại, email và địa chỉ giao hàng do khách hàng cung cấp khi đăng ký tài khoản hoặc đặt hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
                <div>
                  <strong>Thông tin thanh toán</strong>
                  <p>Thông tin thanh toán chỉ được sử dụng để xử lý giao dịch và không được lưu trữ trái phép.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="bao-ve" class="policy-section" aria-labelledby="bao-ve-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
              <h2 id="bao-ve-title">Cách chúng tôi bảo vệ thông tin</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                <div>
                  <strong>Công nghệ bảo mật</strong>
                  <p>Áp dụng mã hóa dữ liệu, tường lửa và các biện pháp bảo mật hiện đại để bảo vệ thông tin.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-user-lock"></i></span>
                <div>
                  <strong>Kiểm soát truy cập</strong>
                  <p>Chỉ nhân sự được ủy quyền mới có quyền tiếp cận thông tin cá nhân của khách hàng.</p>
                </div>
              </li>
            </ul>
          </section>

          <section id="chia-se" class="policy-section" aria-labelledby="chia-se-title">
            <header class="policy-section__header">
              <span class="policy-section__number" aria-hidden="true"><i class="fa-solid fa-share-nodes"></i></span>
              <h2 id="chia-se-title">Chia sẻ thông tin</h2>
            </header>
            <ul class="policy-items">
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-ban"></i></span>
                <div>
                  <strong>Không chia sẻ trái phép</strong>
                  <p>Gấu Bakery cam kết không mua bán, trao đổi thông tin cá nhân khi chưa có sự đồng ý của khách hàng.</p>
                </div>
              </li>
              <li class="policy-item">
                <span class="policy-item__icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                <div>
                  <strong>Tuân thủ pháp luật</strong>
                  <p>Thông tin chỉ được cung cấp cho cơ quan chức năng khi có yêu cầu hợp pháp.</p>
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
