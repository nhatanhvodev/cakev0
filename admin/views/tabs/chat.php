<?php
/* admin/views/tabs/chat.php — expects $_SESSION['csrf_token'] from admin/bootstrap.php */
?>
<div class="card panel" style="margin-bottom:18px;">
  <div class="panel-head">
    <div>
      <h2>Hội Thoại Khách Hàng</h2>
    </div>
  </div>
  <div class="ac-stats" aria-label="Thống kê hội thoại">
    <div class="ac-stat-box">
      <span class="ac-stat-label">Hội thoại hôm nay</span>
      <span class="ac-stat-value" id="ac-stat-today">-</span>
    </div>
    <div class="ac-stat-box">
      <span class="ac-stat-label">Đang chờ hỗ trợ</span>
      <span class="ac-stat-value" id="ac-stat-handoff">-</span>
    </div>
    <div class="ac-stat-box">
      <span class="ac-stat-label">Ý định</span>
      <span class="ac-stat-value" id="ac-stat-intents" style="font-size:13px;">-</span>
    </div>
  </div>
  <div id="admin-chat-root" data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"></div>
</div>
