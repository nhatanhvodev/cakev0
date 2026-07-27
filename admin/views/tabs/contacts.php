<?php
/* admin/views/tabs/contacts.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/modal.php';

// =====================================================================
// Data — ported from admin/admin.php:
//   L1001-1003 contact_requests query with false-query fallback
//   L2858-2927 contacts tab markup
//   L3106-3152 contact detail/reply/delete modals
//   L3755-3824 contact modal JS
// Columns used: contact_requests.id/name/email/phone/message/status/
//   reply_message/created_at/replied_at (schema: database/banh_store.sql L107-119).
// =====================================================================
$contactRequestsQuery = $conn->query("SELECT * FROM contact_requests ORDER BY created_at DESC");
$contactRequests = $contactRequestsQuery ? $contactRequestsQuery->fetch_all(MYSQLI_ASSOC) : [];

$pendingCount = 0;
$repliedCount = 0;
$rowsHtml = '';
foreach ($contactRequests as $c) {
    $contactId = (int) $c['id'];
    $status = (string) ($c['status'] ?? 'pending');
    if ($status === 'replied') {
        $repliedCount++;
    } else {
        $pendingCount++;
    }

    $createdAt = date('d/m/Y H:i', strtotime((string) $c['created_at']));
    $message = (string) $c['message'];
    $statusBadge = $status === 'replied' ? 'contact-replied' : 'contact-pending';

    $replyButton = '';
    if ($status === 'pending') {
        $replyButton = '<button type="button" class="btn btn-primary contact-reply-btn" data-modal-open="contactReplyModal"'
            . ' data-id="' . $contactId . '"'
            . ' data-name="' . htmlspecialchars((string) $c['name'], ENT_QUOTES) . '"'
            . ' data-email="' . htmlspecialchars((string) $c['email'], ENT_QUOTES) . '"'
            . ' title="Phản hồi"><i class="bi bi-reply"></i></button> ';
    }

    $rowsHtml .= '<tr>'
        . '<td><strong>' . htmlspecialchars((string) $c['name']) . '</strong></td>'
        . '<td><small>' . htmlspecialchars((string) $c['email']) . '</small><br><span style="color:var(--muted);font-size:12px;">' . htmlspecialchars((string) $c['phone']) . '</span></td>'
        . '<td><div title="' . htmlspecialchars($message, ENT_QUOTES) . '" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . htmlspecialchars($message) . '</div></td>'
        . '<td><small>' . htmlspecialchars($createdAt) . '</small></td>'
        . '<td>' . render_badge($statusBadge) . '</td>'
        . '<td>'
        . '<button type="button" class="btn btn-ghost contact-detail-btn" data-modal-open="contactDetailModal"'
        . ' data-id="' . $contactId . '"'
        . ' data-name="' . htmlspecialchars((string) $c['name'], ENT_QUOTES) . '"'
        . ' data-email="' . htmlspecialchars((string) $c['email'], ENT_QUOTES) . '"'
        . ' data-phone="' . htmlspecialchars((string) $c['phone'], ENT_QUOTES) . '"'
        . ' data-msg="' . htmlspecialchars($message, ENT_QUOTES) . '"'
        . ' data-status="' . htmlspecialchars($status, ENT_QUOTES) . '"'
        . ' data-reply="' . htmlspecialchars((string) ($c['reply_message'] ?? ''), ENT_QUOTES) . '"'
        . ' data-date="' . htmlspecialchars($createdAt, ENT_QUOTES) . '"'
        . ' title="Chi tiết"><i class="bi bi-eye"></i></button> '
        . $replyButton
        . '<button type="button" class="btn btn-danger contact-delete-btn" data-modal-open="contactDeleteModal"'
        . ' data-id="' . $contactId . '"'
        . ' data-name="' . htmlspecialchars((string) $c['name'], ENT_QUOTES) . '"'
        . ' title="Xóa liên hệ"><i class="bi bi-trash"></i></button>'
        . '</td>'
        . '</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:22px;">Chưa có yêu cầu hỗ trợ nào.</td></tr>';
}
?>
<div class="card panel">
  <div class="panel-head">
    <div>
      <h2>Yêu Cầu Hỗ Trợ Khách Hàng</h2>
      <div class="sub"><?= $pendingCount ?> chờ xử lý / <?= $repliedCount ?> đã phản hồi</div>
    </div>
  </div>
  <?php render_table(['Tên', 'Email/SĐT', 'Nội dung', 'Ngày gửi', 'Trạng thái', 'Hành động'], $rowsHtml); ?>
</div>

<?php
$contactDetailBody = '<div id="contactDetailBody"></div>';
$contactDetailActions = '<button type="button" class="btn btn-ghost" data-modal-close>Đóng</button>';
render_modal('contactDetailModal', 'Chi tiết yêu cầu hỗ trợ', $contactDetailBody, $contactDetailActions);

$contactReplyBody = '<form method="POST" id="contactReplyForm" style="display:grid;gap:14px;">'
    . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
    . '<input type="hidden" name="tab" value="contacts">'
    . '<input type="hidden" name="contact_id" id="replyContactId">'
    . '<input type="hidden" name="contact_email" id="replyContactEmail">'
    . '<input type="hidden" name="contact_name" id="replyContactName">'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Người nhận</label>'
    . '<input type="text" id="displayReplyName" readonly disabled style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--muted);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Nội dung phản hồi</label>'
    . '<textarea name="reply_message" id="replyMessage" rows="6" placeholder="Nhập nội dung phản hồi cho khách hàng..." required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);resize:vertical;"></textarea></div>'
    . '</form>';
$contactReplyActions = '<button type="button" class="btn btn-ghost" data-modal-close>Hủy</button>'
    . '<button type="submit" form="contactReplyForm" name="reply_contact" class="btn btn-primary"><i class="bi bi-send"></i> Gửi phản hồi</button>';
render_modal('contactReplyModal', 'Phản hồi khách hàng', $contactReplyBody, $contactReplyActions);

$contactDeleteBody = '<p id="contactDeleteDesc">Yêu cầu hỗ trợ này sẽ bị xóa khỏi hệ thống.</p>'
    . '<form method="POST" id="contactDeleteForm">'
    . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
    . '<input type="hidden" name="tab" value="contacts">'
    . '<input type="hidden" name="contact_id" id="deleteContactId">'
    . '</form>';
$contactDeleteActions = '<button type="button" class="btn btn-ghost" data-modal-close>Hủy</button>'
    . '<button type="submit" form="contactDeleteForm" name="delete_contact" class="btn btn-danger"><i class="bi bi-trash"></i> Xóa liên hệ</button>';
render_modal('contactDeleteModal', 'Xóa liên hệ?', $contactDeleteBody, $contactDeleteActions);
?>

<script>
(function () {
  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  document.querySelectorAll('.contact-detail-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = btn.dataset;
      var html =
        '<div style="display:flex;flex-direction:column;gap:8px;">' +
        '<div><strong>Khách hàng:</strong> ' + esc(d.name) + '</div>' +
        '<div><strong>Email:</strong> ' + esc(d.email) + '</div>' +
        '<div><strong>SĐT:</strong> ' + esc(d.phone) + '</div>' +
        '<div><strong>Ngày gửi:</strong> ' + esc(d.date) + '</div>' +
        '<div style="padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--surface-2);">' +
        '<strong>Nội dung thắc mắc:</strong><br>' + esc(d.msg) +
        '</div>';

      if (d.status === 'replied') {
        html += '<div style="padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--success-bg);">' +
          '<strong>Đã phản hồi:</strong><br>' + esc(d.reply) +
          '</div>';
      }

      html += '</div>';
      document.getElementById('contactDetailBody').innerHTML = html;
    });
  });

  document.querySelectorAll('.contact-reply-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('replyContactId').value = btn.dataset.id || '';
      document.getElementById('replyContactEmail').value = btn.dataset.email || '';
      document.getElementById('replyContactName').value = btn.dataset.name || '';
      document.getElementById('displayReplyName').value = (btn.dataset.name || '') + ' (' + (btn.dataset.email || '') + ')';
      document.getElementById('replyMessage').value = '';
    });
  });

  document.querySelectorAll('.contact-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('deleteContactId').value = btn.dataset.id || '';
      document.getElementById('contactDeleteDesc').textContent =
        'Yêu cầu hỗ trợ của ' + (btn.dataset.name || 'khách hàng') + ' sẽ bị xóa khỏi hệ thống.';
    });
  });
})();
</script>
