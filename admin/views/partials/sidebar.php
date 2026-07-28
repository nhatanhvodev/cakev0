<?php /* admin/views/partials/sidebar.php — expects $tab in scope */ ?>
<div class="brand">
  <img class="logo" src="/cakev0/assets/img/logo.png" alt="Gấu Bakery" style="width:34px;height:34px;object-fit:contain;border-radius:9px;">
  <div class="txt"><b>Gấu Bakery</b><span>Bảng quản trị</span></div>
</div>
<nav class="nav">
  <div class="group-label">Tổng quan</div>
  <a<?= $tab === 'dashboard' ? ' class="active"' : '' ?> href="index.php?tab=dashboard#dashboard"><span class="ico"><i class="bi bi-speedometer2"></i></span><span class="label">Tổng quan</span></a>
  <a<?= $tab === 'orders' ? ' class="active"' : '' ?> href="index.php?tab=orders#orders"><span class="ico"><i class="bi bi-cart-check"></i></span><span class="label">Đơn hàng</span></a>
  <a<?= $tab === 'products' ? ' class="active"' : '' ?> href="index.php?tab=products#products"><span class="ico"><i class="bi bi-box-seam"></i></span><span class="label">Sản phẩm</span></a>
  <a<?= $tab === 'best-selling' ? ' class="active"' : '' ?> href="index.php?tab=best-selling#best-selling"><span class="ico"><i class="bi bi-star"></i></span><span class="label">Bán chạy</span></a>
  <a<?= $tab === 'testimonials' ? ' class="active"' : '' ?> href="index.php?tab=testimonials#testimonials"><span class="ico"><i class="bi bi-chat-quote"></i></span><span class="label">Đánh giá</span></a>

  <div class="group-label">Khách hàng</div>
  <a<?= $tab === 'users' ? ' class="active"' : '' ?> href="index.php?tab=users#users"><span class="ico"><i class="bi bi-people"></i></span><span class="label">Người dùng</span></a>
  <a<?= $tab === 'password-requests' ? ' class="active"' : '' ?> href="index.php?tab=password-requests#password-requests"><span class="ico"><i class="bi bi-key"></i></span><span class="label">Yêu cầu mật khẩu</span></a>
  <a<?= $tab === 'contacts' ? ' class="active"' : '' ?> href="index.php?tab=contacts#contacts"><span class="ico"><i class="bi bi-envelope"></i></span><span class="label">Liên hệ</span></a>
  <a<?= $tab === 'chat' ? ' class="active"' : '' ?> href="index.php?tab=chat#chat"><span class="ico"><i class="bi bi-chat-dots"></i></span><span class="label">Chat hỗ trợ</span></a>

  <div class="group-label">Khuyến mãi</div>
  <a<?= $tab === 'promotions' ? ' class="active"' : '' ?> href="index.php?tab=promotions#promotions"><span class="ico"><i class="bi bi-tag"></i></span><span class="label">Khuyến mãi</span></a>
  <a<?= $tab === 'coupons' ? ' class="active"' : '' ?> href="index.php?tab=coupons#coupons"><span class="ico"><i class="bi bi-ticket-perforated"></i></span><span class="label">Mã giảm giá</span></a>
</nav>
