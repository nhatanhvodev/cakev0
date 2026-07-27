<?php
/* admin/views/tabs/coupons.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/modal.php';

// =====================================================================
// Data — ported from admin/admin.php:
//   L999       coupons list query (cart_coupons ORDER BY created_at DESC, id DESC)
//   L2755-2855 coupons tab markup: add form + table + edit/delete buttons
//   L3048-3092 editCouponModal
//   L3531-3541 edit modal field population JS
// Columns used: cart_coupons.id/code/discount_percent/min_subtotal/usage_limit/
//   used_count/is_active/starts_at/ends_at/created_at (created by
//   config/coupons.php::ensureCartCouponInfrastructure()).
// =====================================================================
$coupons = $conn->query("SELECT * FROM cart_coupons ORDER BY created_at DESC, id DESC")->fetch_all(MYSQLI_ASSOC);

function admin_coupon_percent_label($value): string
{
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . '%';
}

function admin_coupon_date_label($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Không giới hạn';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

$activeCount = 0;
$inactiveCount = 0;
$rowsHtml = '';
foreach ($coupons as $coupon) {
    $couponId = (int) $coupon['id'];
    $isActive = !empty($coupon['is_active']);
    if ($isActive) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }

    $startValue = !empty($coupon['starts_at']) ? formatCouponDateValue($coupon['starts_at']) : '';
    $endValue = !empty($coupon['ends_at']) ? formatCouponDateValue($coupon['ends_at']) : '';
    $startLabel = admin_coupon_date_label($coupon['starts_at'] ?? '');
    $endLabel = admin_coupon_date_label($coupon['ends_at'] ?? '');
    $deleteUrl = '?tab=coupons&delete_coupon_id=' . $couponId . '&csrf=' . urlencode($_SESSION['csrf_token']);

    $rowsHtml .= '<tr>'
        . '<td><strong>' . htmlspecialchars((string) $coupon['code'], ENT_QUOTES) . '</strong></td>'
        . '<td>' . admin_coupon_percent_label($coupon['discount_percent']) . '</td>'
        . '<td>' . number_format((float) ($coupon['min_subtotal'] ?? 0), 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . htmlspecialchars(couponUsageLabel($coupon)) . '</td>'
        . '<td>' . htmlspecialchars($startLabel) . ' -> ' . htmlspecialchars($endLabel) . '</td>'
        . '<td>' . render_badge($isActive ? 'coupon-active' : 'coupon-inactive') . '</td>'
        . '<td>'
        . '<button type="button" class="btn btn-ghost coupon-edit-btn" data-modal-open="editCouponModal"'
        . ' data-id="' . $couponId . '"'
        . ' data-code="' . htmlspecialchars((string) $coupon['code'], ENT_QUOTES) . '"'
        . ' data-discount="' . (float) $coupon['discount_percent'] . '"'
        . ' data-min-subtotal="' . (float) ($coupon['min_subtotal'] ?? 0) . '"'
        . ' data-usage-limit="' . (int) ($coupon['usage_limit'] ?? 0) . '"'
        . ' data-start="' . htmlspecialchars($startValue) . '"'
        . ' data-end="' . htmlspecialchars($endValue) . '"'
        . ' data-active="' . ($isActive ? '1' : '0') . '"'
        . ' title="Sửa coupon"><i class="bi bi-pencil"></i></button> '
        . '<button type="button" class="btn btn-danger" data-delete-url="' . htmlspecialchars($deleteUrl) . '"'
        . ' data-confirm-text="' . htmlspecialchars((string) $coupon['code'], ENT_QUOTES) . ' sẽ bị xóa vĩnh viễn và không thể khôi phục."'
        . ' title="Xóa coupon"><i class="bi bi-trash"></i></button>'
        . '</td>'
        . '</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:22px;">Chưa có coupon nào.</td></tr>';
}
?>
<div class="card panel" style="margin-bottom:18px;">
  <div class="panel-head">
    <div>
      <h2>Thêm Coupon</h2>
      <div class="sub">Mã giảm giá áp dụng trong giỏ hàng.</div>
    </div>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="tab" value="coupons">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end;">
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Mã coupon</label>
        <input type="text" name="code" maxlength="30" required style="width:100%;text-transform:uppercase;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">% giảm giá</label>
        <input type="number" name="discount_percent" min="1" max="100" step="0.01" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Đơn tối thiểu</label>
        <input type="number" name="min_subtotal" min="0" step="1000" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giới hạn lượt dùng</label>
        <input type="number" name="usage_limit" min="1" step="1" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày bắt đầu</label>
        <input type="date" name="starts_at" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày kết thúc</label>
        <input type="date" name="ends_at" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <label style="display:flex;align-items:center;gap:8px;color:var(--text);font-size:13px;">
        <input type="checkbox" name="is_active" checked> Đang hoạt động
      </label>
      <button type="submit" name="add_coupon" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm</button>
    </div>
  </form>
</div>

<div class="card panel">
  <div class="panel-head">
    <h2>Mã Giảm Giá Coupon</h2>
    <div class="sub"><?= $activeCount ?> hoạt động / <?= $inactiveCount ?> tạm tắt</div>
  </div>
  <?php render_table(['Mã', 'Giảm giá', 'Đơn tối thiểu', 'Lượt dùng', 'Thời gian', 'Trạng thái', 'Hành động'], $rowsHtml); ?>
</div>

<?php
$editCouponBody = '<form method="POST" id="editCouponForm" style="display:grid;gap:14px;">'
    . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
    . '<input type="hidden" name="tab" value="coupons">'
    . '<input type="hidden" name="coupon_id" id="editCouponId">'
    . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Mã coupon</label>'
    . '<input type="text" name="code" id="editCouponCode" maxlength="30" required style="width:100%;text-transform:uppercase;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">% giảm giá</label>'
    . '<input type="number" name="discount_percent" id="editCouponDiscount" min="1" max="100" step="0.01" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Đơn tối thiểu (VNĐ)</label>'
    . '<input type="number" name="min_subtotal" id="editCouponMinSubtotal" min="0" step="1000" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giới hạn lượt dùng</label>'
    . '<input type="number" name="usage_limit" id="editCouponUsageLimit" min="1" step="1" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày bắt đầu</label>'
    . '<input type="date" name="starts_at" id="editCouponStart" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày kết thúc</label>'
    . '<input type="date" name="ends_at" id="editCouponEnd" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '</div>'
    . '<label style="display:flex;align-items:center;gap:8px;color:var(--text);font-size:13px;"><input type="checkbox" name="is_active" id="editCouponActive"> Đang hoạt động</label>'
    . '</form>';
$editCouponActions = '<button type="button" class="btn btn-ghost" data-modal-close>Hủy</button>'
    . '<button type="submit" form="editCouponForm" name="update_coupon" class="btn btn-primary"><i class="bi bi-save"></i> Lưu thay đổi</button>';
render_modal('editCouponModal', 'Chỉnh sửa coupon', $editCouponBody, $editCouponActions);
?>

<script>
(function () {
  document.querySelectorAll('.coupon-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('editCouponId').value = btn.dataset.id || '';
      document.getElementById('editCouponCode').value = btn.dataset.code || '';
      document.getElementById('editCouponDiscount').value = btn.dataset.discount || '';
      document.getElementById('editCouponMinSubtotal').value = btn.dataset.minSubtotal || '0';
      document.getElementById('editCouponUsageLimit').value = btn.dataset.usageLimit === '0' ? '' : (btn.dataset.usageLimit || '');
      document.getElementById('editCouponStart').value = btn.dataset.start || '';
      document.getElementById('editCouponEnd').value = btn.dataset.end || '';
      document.getElementById('editCouponActive').checked = btn.dataset.active === '1';
    });
  });
})();
</script>
