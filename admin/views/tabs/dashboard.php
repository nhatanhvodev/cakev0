<?php
/* admin/views/tabs/dashboard.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/stat_card.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';

// =====================================================================
// Data computation — ported verbatim from admin/admin.php ~L791-1109
// (orders query, revenue totals, chart bucketing). Do not alter the SQL.
// =====================================================================

$orders = $conn->query(
    "SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$total_revenue = 0;
$pending_count = 0;
$js_dates = '[]';
$js_revenues = '[]';
$chart_view = $_GET['chart_view'] ?? '7days';
$chart_view = in_array($chart_view, ['7days', 'month', 'year'], true) ? $chart_view : '7days';
$selected_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$selected_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$current_year = (int) date('Y');
$selected_year = ($selected_year < 2000 || $selected_year > $current_year + 1) ? $current_year : $selected_year;
$selected_month = ($selected_month < 1 || $selected_month > 12) ? (int) date('m') : $selected_month;
$chart_title = 'Biểu đồ doanh thu 7 ngày qua';
$chart_labels = [];

$chart_data = [];
if ($chart_view === 'month') {
    $days_in_month = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $selected_year, $selected_month)))
        ->format('t');
    for ($day = 1; $day <= $days_in_month; $day++) {
        $chart_data[$day] = 0;
    }
    $chart_title = "Biểu đồ doanh thu tháng {$selected_month}/{$selected_year}";
} elseif ($chart_view === 'year') {
    for ($month = 1; $month <= 12; $month++) {
        $chart_data[$month] = 0;
    }
    $chart_title = "Biểu đồ doanh thu năm {$selected_year}";
} else {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data[$date] = 0;
    }
}

foreach ($orders as $o) {
    $status = strtolower($o['status']);
    $is_revenue = in_array($status, ['paid', 'approved', 'delivered', 'completed'], true);

    if ($is_revenue) {
        $total_revenue += $o['total_amount'];

        $order_date = strtotime($o['created_at']);
        $order_year = (int) date('Y', $order_date);
        $order_month = (int) date('n', $order_date);
        $order_day = (int) date('j', $order_date);

        if ($chart_view === 'month') {
            if ($order_year === $selected_year && $order_month === $selected_month) {
                $chart_data[$order_day] += $o['total_amount'];
            }
        } elseif ($chart_view === 'year') {
            if ($order_year === $selected_year) {
                $chart_data[$order_month] += $o['total_amount'];
            }
        } else {
            $order_key = date('Y-m-d', $order_date);
            if (isset($chart_data[$order_key])) {
                $chart_data[$order_key] += $o['total_amount'];
            }
        }
    }
    if (in_array($status, ['pending', 'cod_not_deposited'], true)) {
        $pending_count++;
    }
}

// Chuyển dữ liệu sang JSON để JS sử dụng
$chart_values = array_values($chart_data);
if ($chart_view === 'month') {
    foreach (array_keys($chart_data) as $day) {
        $chart_labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }
} elseif ($chart_view === 'year') {
    foreach (array_keys($chart_data) as $month) {
        $chart_labels[] = 'T' . $month;
    }
} else {
    foreach (array_keys($chart_data) as $date) {
        $chart_labels[] = date('d/m', strtotime($date));
    }
}

$js_dates = json_encode($chart_labels);
$js_revenues = json_encode($chart_values);

// =====================================================================
// Best-selling — ported from admin/admin.php ~L1026-1037 (same JOIN +
// WHERE as $bestSalesRows there), extended with a join to banh.ten_banh
// and capped to the top 3 for this widget.
// =====================================================================
$topSellingRows = $conn->query(
    "SELECT b.ten_banh, SUM(oi.quantity) AS sold_qty
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN banh b ON b.id = oi.banh_id
     WHERE o.status IN ('paid','approved','delivered','completed')
     GROUP BY oi.banh_id, b.ten_banh
     ORDER BY sold_qty DESC
     LIMIT 3"
)->fetch_all(MYSQLI_ASSOC);

// --- summary counts (reuse existing $conn) ---
// Columns verified against admin.php: orders.status (L950,1030,1062,1087),
// users.created_at (L954 "ORDER BY u.created_at DESC").
$orderCount = (int) ($conn->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] ?? 0);
$pendingCount = (int) ($conn->query("SELECT COUNT(*) c FROM orders WHERE LOWER(status)='pending'")->fetch_assoc()['c'] ?? 0);
$newUsers = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c'] ?? 0);

// =====================================================================
// Recent orders — columns confirmed against admin.php L959 (orders o LEFT
// JOIN users u ON o.user_id = u.id) and L971-978/2094-2096 (o.id,
// o.recipient_name, o.total_amount, o.status, o.created_at; u.username as
// display name, falling back to recipient_name when there is no account —
// mirrors legacy's `$o['username'] ?? 'Khách lẻ'` at admin.php L2095).
// =====================================================================
$recentOrders = $conn->query(
    "SELECT o.id, o.recipient_name, u.username, o.total_amount, o.status, o.created_at
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC
     LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$rowsHtml = '';
foreach ($recentOrders as $o) {
    $customerName = !empty($o['username']) ? $o['username'] : $o['recipient_name'];
    $rowsHtml .= '<tr>'
        . '<td>#' . (int) $o['id'] . '</td>'
        . '<td>' . htmlspecialchars((string) $customerName) . '</td>'
        . '<td>' . number_format((float) $o['total_amount'], 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . render_badge((string) $o['status']) . '</td>'
        . '<td>' . htmlspecialchars(date('d/m/Y', strtotime((string) $o['created_at']))) . '</td>'
        . '</tr>';
}
?>
<div class="kpi-row">
  <?php
    render_stat_card('Doanh thu tháng', number_format($total_revenue, 0, ',', '.') . ' VNĐ', 'so với kỳ trước', 'up', 'bi-cash-coin');
    render_stat_card('Đơn hàng', number_format($orderCount, 0, ',', '.'), 'tổng đơn', 'up', 'bi-cart-check');
    render_stat_card('Người dùng mới', number_format($newUsers, 0, ',', '.'), '30 ngày qua', 'up', 'bi-people');
    render_stat_card('Đơn chờ xử lý', number_format($pendingCount, 0, ',', '.'), 'cần duyệt', $pendingCount > 0 ? 'down' : 'up', 'bi-hourglass-split');
  ?>
</div>
<div class="row-2">
  <div class="card panel">
    <div class="panel-head"><div><h2>Doanh thu</h2><div class="sub"><?= htmlspecialchars($chart_title) ?></div></div></div>
    <div style="height:260px"><canvas id="revenueChart"></canvas></div>
  </div>
  <div class="card panel">
    <div class="panel-head"><h2>Bán chạy nhất</h2></div>
    <div class="statlist">
      <?php if (empty($topSellingRows)): ?>
        <div class="item"><span>Chưa có dữ liệu bán hàng</span></div>
      <?php else: ?>
        <?php foreach ($topSellingRows as $row): ?>
          <div class="item">
            <span><?= htmlspecialchars((string) $row['ten_banh']) ?></span>
            <strong><?= (int) $row['sold_qty'] ?> đã bán</strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="card panel">
  <div class="panel-head"><div><h2>Đơn hàng gần đây</h2></div><a class="rowlink" href="index.php?tab=orders">Xem tất cả →</a></div>
  <?php render_table(['Mã đơn', 'Khách hàng', 'Tổng', 'Trạng thái', 'Ngày'], $rowsHtml); ?>
</div>
<script>window.ADMIN_CHART = {labels: <?= $js_dates ?>, values: <?= $js_revenues ?>};</script>
