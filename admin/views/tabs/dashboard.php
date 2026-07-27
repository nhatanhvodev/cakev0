<?php
/* admin/views/tabs/dashboard.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/stat_card.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../../lib/revenue_report.php';

$report = admin_revenue_report($conn, admin_revenue_params($_GET));
$params = $report['params'];
$currentYear = (int) date('Y');

$topSellingRows = array_slice($report['top_products'], 0, 3);
$recentOrders = $report['recent_orders'];

$rowsHtml = '';
foreach ($recentOrders as $o) {
    $customerName = !empty($o['username']) ? $o['username'] : $o['recipient_name'];
    $rowsHtml .= '<tr>'
        . '<td>#' . (int) $o['id'] . '</td>'
        . '<td>' . htmlspecialchars((string) $customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>'
        . '<td>' . admin_revenue_vnd((float) $o['total_amount']) . '</td>'
        . '<td>' . render_badge((string) $o['status']) . '</td>'
        . '<td>' . htmlspecialchars(date('d/m/Y', strtotime((string) $o['created_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>'
        . '</tr>';
}
if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:22px;">Chưa có đơn hàng.</td></tr>';
}

$monthOptions = '';
for ($m = 1; $m <= 12; $m++) {
    $monthOptions .= '<option value="' . $m . '"' . ($m === $params['month'] ? ' selected' : '') . '>Tháng ' . $m . '</option>';
}

$yearOptions = '';
for ($y = $currentYear + 1; $y >= max(2000, $currentYear - 5); $y--) {
    $yearOptions .= '<option value="' . $y . '"' . ($y === $params['year'] ? ' selected' : '') . '>' . $y . '</option>';
}

$chartPayload = [
    'labels' => $report['labels'],
    'values' => $report['values'],
    'orderCounts' => $report['order_counts'],
    'title' => $report['title'],
];
?>
<div class="kpi-row">
  <?php
    render_stat_card('Tổng doanh thu', admin_revenue_vnd((float) $report['total_revenue']), 'đơn đã thanh toán/xác nhận', 'up', 'bi-cash-coin');
    render_stat_card('Đơn hàng', number_format((int) $report['total_order_count'], 0, ',', '.'), 'tổng đơn', 'up', 'bi-cart-check');
    render_stat_card('Giá trị đơn TB', admin_revenue_vnd((float) $report['avg_order_value']), 'AOV doanh thu', 'up', 'bi-graph-up');
    render_stat_card('Đơn chờ xử lý', number_format((int) $report['pending_count'], 0, ',', '.'), 'cần duyệt', $report['pending_count'] > 0 ? 'down' : 'up', 'bi-hourglass-split');
  ?>
</div>

<div class="row-2">
  <div class="card panel revenue-card">
    <div class="revenue-head">
      <div>
        <h2>Doanh thu</h2>
        <div class="sub"><?= htmlspecialchars($report['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <form method="GET" class="revenue-toolbar" data-revenue-toolbar>
        <input type="hidden" name="tab" value="dashboard">
        <div class="seg revenue-seg" role="radiogroup" aria-label="Khoảng thời gian doanh thu">
          <?php foreach (['week' => 'Tuần', 'month' => 'Tháng', 'year' => 'Năm'] as $value => $label): ?>
            <label>
              <input type="radio" name="chart_view" value="<?= $value ?>" <?= $params['chart_view'] === $value ? 'checked' : '' ?>>
              <span><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <label class="revenue-select revenue-filter-month" <?= $params['chart_view'] === 'month' ? '' : 'hidden' ?>>
          <span>Tháng</span>
          <select name="month"><?= $monthOptions ?></select>
        </label>
        <label class="revenue-select revenue-filter-year" <?= in_array($params['chart_view'], ['month', 'year'], true) ? '' : 'hidden' ?>>
          <span>Năm</span>
          <select name="year"><?= $yearOptions ?></select>
        </label>
        <button type="submit" class="btn btn-primary revenue-apply"><i class="bi bi-arrow-repeat"></i> Áp dụng</button>
      </form>
    </div>

    <div class="chart-metrics" aria-label="Tổng quan doanh thu">
      <div><span>Doanh thu kỳ này</span><strong><?= admin_revenue_vnd((float) array_sum($report['values'])) ?></strong></div>
      <div><span>Đơn tính doanh thu</span><strong><?= number_format((int) array_sum($report['order_counts']), 0, ',', '.') ?></strong></div>
      <div><span>Người dùng mới</span><strong><?= number_format((int) $report['new_users'], 0, ',', '.') ?></strong></div>
    </div>

    <div class="chart-shell">
      <canvas id="revenueChart" aria-label="Biểu đồ doanh thu" role="img"></canvas>
    </div>

    <div class="report-actions" aria-label="Xuất báo cáo doanh thu">
      <a class="btn btn-ghost" href="<?= htmlspecialchars(admin_revenue_export_url('xlsx', $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
      <a class="btn btn-ghost" href="<?= htmlspecialchars(admin_revenue_export_url('csv', $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
      <a class="btn btn-ghost" href="<?= htmlspecialchars(admin_revenue_export_url('pdf', $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><i class="bi bi-filetype-pdf"></i> PDF</a>
    </div>
  </div>

  <div class="card panel">
    <div class="panel-head"><h2>Bán chạy nhất</h2></div>
    <div class="statlist">
      <?php if (empty($topSellingRows)): ?>
        <div class="item"><span>Chưa có dữ liệu bán hàng</span></div>
      <?php else: ?>
        <?php foreach ($topSellingRows as $row): ?>
          <div class="item">
            <span><?= htmlspecialchars((string) $row['ten_banh'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <strong><?= (int) $row['sold_qty'] ?> đã bán</strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card panel">
  <div class="panel-head"><div><h2>Đơn hàng gần đây</h2></div><a class="rowlink" href="index.php?tab=orders">Xem tất cả</a></div>
  <?php render_table(['Mã đơn', 'Khách hàng', 'Tổng', 'Trạng thái', 'Ngày'], $rowsHtml); ?>
</div>

<script>
window.ADMIN_CHART = <?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
