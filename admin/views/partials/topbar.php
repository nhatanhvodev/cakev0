<?php
/* admin/views/partials/topbar.php — expects $tab, $conn in scope */
require_once __DIR__ . '/../../lib/topbar.php';

$topbarData = admin_topbar_data($conn);
$tabMeta = [
    'dashboard' => ['crumb' => 'Trang chủ · Tổng quan', 'title' => 'Bảng điều khiển'],
    'orders' => ['crumb' => 'Vận hành · Đơn hàng', 'title' => 'Đơn hàng'],
    'products' => ['crumb' => 'Danh mục · Sản phẩm', 'title' => 'Sản phẩm'],
    'best-selling' => ['crumb' => 'Danh mục · Bán chạy', 'title' => 'Bán chạy'],
    'testimonials' => ['crumb' => 'Khách hàng · Đánh giá', 'title' => 'Đánh giá'],
    'password-requests' => ['crumb' => 'Khách hàng · Bảo mật', 'title' => 'Yêu cầu mật khẩu'],
    'users' => ['crumb' => 'Khách hàng · Người dùng', 'title' => 'Người dùng'],
    'promotions' => ['crumb' => 'Khuyến mãi · Campaign', 'title' => 'Khuyến mãi'],
    'coupons' => ['crumb' => 'Khuyến mãi · Coupon', 'title' => 'Mã giảm giá'],
    'contacts' => ['crumb' => 'Hỗ trợ · Liên hệ', 'title' => 'Liên hệ'],
    'chat' => ['crumb' => 'Hỗ trợ · Hội thoại', 'title' => 'Chat hỗ trợ'],
];
$meta = $tabMeta[$tab ?? 'dashboard'] ?? $tabMeta['dashboard'];
$notificationCount = (int) $topbarData['notification_count'];
?>
<header class="topbar">
  <button class="toggle" id="collapseBtn" title="Thu gọn menu" aria-label="Thu gọn menu" aria-controls="adminSidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
  <div class="titlewrap">
    <div class="crumb"><?= htmlspecialchars($meta['crumb'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <h1><?= htmlspecialchars($meta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
  </div>

  <div class="search admin-search" id="adminSearch">
    <span><i class="bi bi-search"></i></span>
    <input id="adminSearchInput" type="search" autocomplete="off" placeholder="Tìm đơn hàng, sản phẩm, khách..." aria-label="Tìm trong admin" aria-controls="adminSearchPanel" aria-expanded="false">
    <div class="topbar-popover search-panel" id="adminSearchPanel" hidden>
      <div class="topbar-popover__head">Kết quả nhanh</div>
      <div class="search-results" id="adminSearchResults" role="listbox"></div>
      <div class="topbar-empty" id="adminSearchEmpty" hidden>Không tìm thấy dữ liệu phù hợp.</div>
    </div>
  </div>

  <div class="topbar-menu">
    <button class="iconbtn" id="notifyBtn" title="Thông báo" aria-label="Thông báo" aria-haspopup="true" aria-expanded="false">
      <i class="bi bi-bell"></i>
      <?php if ($notificationCount > 0): ?><span class="count-dot"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span><?php endif; ?>
    </button>
    <div class="topbar-popover notify-panel" id="notifyPanel" hidden>
      <div class="topbar-popover__head">Thông báo vận hành</div>
      <?php foreach ($topbarData['notifications'] as $item): ?>
        <a class="notify-item" href="<?= htmlspecialchars($item['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <span class="notify-icon"><i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i></span>
          <span><strong><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><small><?= (int) $item['count'] ?> mục cần xem</small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <button class="iconbtn" id="themeBtn" title="Đổi giao diện sáng/tối" aria-label="Đổi giao diện sáng/tối"><i class="bi bi-moon"></i></button>

  <div class="topbar-menu">
    <button class="avatar" id="profileBtn" title="<?= htmlspecialchars($topbarData['admin_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Tài khoản admin" aria-haspopup="true" aria-expanded="false">
      <?= htmlspecialchars($topbarData['admin_initials'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </button>
    <div class="topbar-popover profile-panel" id="profilePanel" hidden>
      <div class="profile-summary">
        <div class="avatar avatar--large"><?= htmlspecialchars($topbarData['admin_initials'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div><strong><?= htmlspecialchars($topbarData['admin_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><small><?= htmlspecialchars($topbarData['admin_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small></div>
      </div>
      <a class="profile-action" href="index.php?tab=dashboard"><i class="bi bi-speedometer2"></i> Tổng quan</a>
      <a class="profile-action" href="../pages/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
  </div>
</header>
<script>
window.ADMIN_TOPBAR = <?= json_encode([
  'search' => $topbarData['search'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
