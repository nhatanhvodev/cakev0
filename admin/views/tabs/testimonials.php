<?php
/* admin/views/tabs/testimonials.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';

// =====================================================================
// Data — ported from admin/admin.php:
//   L1018      reviews list query (SELECT * FROM reviews ORDER BY timestamp DESC)
//   L2557-2618 testimonials tab markup: review moderation table with approve
//              and reject forms for pending reviews.
// Columns used: reviews.id/name/text/stars/user_id/status/timestamp
//   (schema: database/banh_store.sql L344-354).
// =====================================================================
$reviews = $conn->query("SELECT * FROM reviews ORDER BY timestamp DESC")->fetch_all(MYSQLI_ASSOC);

$reviewStatusMap = [
    'pending' => ['badge' => 'pending', 'label' => 'Chờ duyệt'],
    'approved' => ['badge' => 'review-approved', 'label' => 'Đã duyệt'],
    'rejected' => ['badge' => 'rejected', 'label' => 'Đã từ chối'],
];

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

$rowsHtml = '';
foreach ($reviews as $r) {
    $reviewId = (int) $r['id'];
    $status = (string) ($r['status'] ?? 'pending');
    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }

    $statusData = $reviewStatusMap[$status] ?? ['badge' => 'unknown', 'label' => 'Không rõ'];

    $actionsHtml = '<span style="color:var(--muted);font-size:12.5px;">Đã xử lý</span>';
    if ($status === 'pending') {
        $actionsHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            . '<form method="POST" style="display:inline-flex;">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
            . '<input type="hidden" name="tab" value="testimonials">'
            . '<input type="hidden" name="review_id" value="' . $reviewId . '">'
            . '<input type="hidden" name="review_status" value="approved">'
            . '<button type="submit" name="update_review_status" class="btn btn-primary" title="Duyệt đánh giá"><i class="bi bi-check-lg"></i></button>'
            . '</form>'
            . '<form method="POST" style="display:inline-flex;">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
            . '<input type="hidden" name="tab" value="testimonials">'
            . '<input type="hidden" name="review_id" value="' . $reviewId . '">'
            . '<input type="hidden" name="review_status" value="rejected">'
            . '<button type="submit" name="update_review_status" class="btn btn-danger" title="Từ chối đánh giá"><i class="bi bi-x-lg"></i></button>'
            . '</form>'
            . '</div>';
    }

    $rowsHtml .= '<tr>'
        . '<td><strong>' . htmlspecialchars((string) $r['name']) . '</strong></td>'
        . '<td style="max-width:520px;white-space:normal;line-height:1.5;">' . htmlspecialchars((string) $r['text']) . '</td>'
        . '<td><span style="color:var(--warn);font-weight:700;">' . htmlspecialchars((string) $r['stars']) . '</span></td>'
        . '<td>' . render_badge($statusData['badge']) . '</td>'
        . '<td>' . $actionsHtml . '</td>'
        . '</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:22px;">Chưa có đánh giá nào.</td></tr>';
}
?>
<div class="card panel">
  <div class="panel-head">
    <div>
      <h2>Duyệt Đánh Giá Khách Hàng</h2>
      <div class="sub">Quản lý lời chứng thực hiển thị trên website.</div>
    </div>
  </div>

  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Chờ duyệt</span></div>
      <div class="val num"><?= $pendingCount ?></div>
    </div>
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Đã duyệt</span></div>
      <div class="val num"><?= $approvedCount ?></div>
    </div>
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Từ chối</span></div>
      <div class="val num"><?= $rejectedCount ?></div>
    </div>
  </div>

  <?php render_table(['Tên', 'Nội dung', 'Sao', 'Trạng thái', 'Thao tác'], $rowsHtml); ?>
</div>
