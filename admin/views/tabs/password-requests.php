<?php
/* admin/views/tabs/password-requests.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';

// =====================================================================
// Data — ported from admin/admin.php:
//   L1005-1016 password request label map + query
//   L2622-2686 password-requests tab markup: table with approve/reject forms.
// Columns used: password_reset_requests.id/user_id/status/created_at/approved_at
//   (schema: database/banh_store.sql L242-252); users.username/email.
// =====================================================================
$passwordRequestsQuery = $conn->query(
    "SELECT r.id, r.user_id, r.status, r.created_at, r.approved_at, u.username, u.email
     FROM password_reset_requests r
     JOIN users u ON r.user_id = u.id
     ORDER BY r.created_at DESC"
);
$passwordRequests = $passwordRequestsQuery ? $passwordRequestsQuery->fetch_all(MYSQLI_ASSOC) : [];

$passwordRequestBadgeKeys = [
    'pending' => 'pending',
    'approved' => 'request-approved',
    'rejected' => 'rejected',
];

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

function format_password_request_time(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d/m/Y H:i', $timestamp);
}

$rowsHtml = '';
foreach ($passwordRequests as $request) {
    $requestId = (int) $request['id'];
    $status = (string) ($request['status'] ?? 'pending');
    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }

    $badgeKey = $passwordRequestBadgeKeys[$status] ?? $status;
    $approvedAt = format_password_request_time($request['approved_at'] ?? null);
    $approvedAtHtml = $approvedAt !== ''
        ? htmlspecialchars($approvedAt)
        : '<span style="color:var(--muted);">Chưa xử lý</span>';

    $actionsHtml = '<span style="color:var(--muted);font-size:12.5px;">Đã xử lý</span>';
    if ($status === 'pending') {
        $actionsHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            . '<form method="POST" style="display:inline-flex;">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
            . '<input type="hidden" name="tab" value="password-requests">'
            . '<input type="hidden" name="request_id" value="' . $requestId . '">'
            . '<input type="hidden" name="request_status" value="approved">'
            . '<button type="submit" name="update_password_request_status" class="btn btn-primary" title="Duyệt yêu cầu"><i class="bi bi-check-lg"></i></button>'
            . '</form>'
            . '<form method="POST" style="display:inline-flex;">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
            . '<input type="hidden" name="tab" value="password-requests">'
            . '<input type="hidden" name="request_id" value="' . $requestId . '">'
            . '<input type="hidden" name="request_status" value="rejected">'
            . '<button type="submit" name="update_password_request_status" class="btn btn-danger" title="Từ chối yêu cầu"><i class="bi bi-x-lg"></i></button>'
            . '</form>'
            . '</div>';
    }

    $rowsHtml .= '<tr>'
        . '<td>#' . $requestId . '</td>'
        . '<td><strong>' . htmlspecialchars((string) $request['username']) . '</strong></td>'
        . '<td>' . htmlspecialchars((string) ($request['email'] ?? '')) . '</td>'
        . '<td>' . render_badge($badgeKey) . '</td>'
        . '<td><small>' . htmlspecialchars(format_password_request_time((string) ($request['created_at'] ?? ''))) . '</small></td>'
        . '<td><small>' . $approvedAtHtml . '</small></td>'
        . '<td>' . $actionsHtml . '</td>'
        . '</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:22px;">Chưa có yêu cầu đổi mật khẩu nào.</td></tr>';
}
?>
<div class="card panel">
  <div class="panel-head">
    <div>
      <h2>Duyệt Yêu Cầu Đổi Mật Khẩu</h2>
      <div class="sub">Phê duyệt hoặc từ chối các yêu cầu đặt lại mật khẩu đã được người dùng gửi.</div>
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

  <?php render_table(['ID', 'Người dùng', 'Email', 'Trạng thái', 'Yêu cầu lúc', 'Phê duyệt lúc', 'Hành động'], $rowsHtml); ?>
</div>
