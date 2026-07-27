<?php
/* admin/views/tabs/promotions.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/modal.php';

// =====================================================================
// Data — ported from admin/admin.php:
//   L948       products list query for the add-promotion product selector
//   L998       promotions list query (p.*, b.ten_banh, b.gia AS gia_hien_tai)
//   L2689-2753 promotions tab markup: add form + list + edit/delete buttons
//   L3004-3033 editPromotionModal
//   L3205-3225 promotion current-price sync JS
//   L3446-3453 edit modal field population JS
// Columns used: promotions.id/banh_id/gia_khuyen_mai/ngay_bat_dau/ngay_ket_thuc
//   (schema: database/banh_store.sql L324-332); banh.id/ten_banh/gia.
// =====================================================================
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$promotions = $conn->query(
    "SELECT p.*, b.ten_banh, b.gia AS gia_hien_tai FROM promotions p JOIN banh b ON p.banh_id = b.id"
)->fetch_all(MYSQLI_ASSOC);

$productOptionsHtml = '';
foreach ($products as $p) {
    $productOptionsHtml .= '<option value="' . (int) $p['id'] . '" data-price="' . (float) $p['gia'] . '">'
        . htmlspecialchars((string) $p['ten_banh']) . '</option>';
}

$rowsHtml = '';
foreach ($promotions as $promo) {
    $promotionId = (int) $promo['id'];
    $startDate = date('Y-m-d', strtotime((string) $promo['ngay_bat_dau']));
    $endDate = date('Y-m-d', strtotime((string) $promo['ngay_ket_thuc']));
    $deleteUrl = '?tab=promotions&delete_promotion_id=' . $promotionId . '&csrf=' . urlencode($_SESSION['csrf_token']);

    $rowsHtml .= '<tr>'
        . '<td><strong>' . htmlspecialchars((string) $promo['ten_banh']) . '</strong></td>'
        . '<td>' . number_format((float) ($promo['gia_hien_tai'] ?? 0), 0, ',', '.') . ' VNĐ</td>'
        . '<td style="font-weight:700;">' . number_format((float) $promo['gia_khuyen_mai'], 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . date('d/m', strtotime((string) $promo['ngay_bat_dau'])) . ' -> ' . date('d/m', strtotime((string) $promo['ngay_ket_thuc'])) . '</td>'
        . '<td>'
        . '<button type="button" class="btn btn-ghost promo-edit-btn" data-modal-open="editPromotionModal"'
        . ' data-id="' . $promotionId . '"'
        . ' data-price="' . (int) $promo['gia_khuyen_mai'] . '"'
        . ' data-start="' . htmlspecialchars($startDate) . '"'
        . ' data-end="' . htmlspecialchars($endDate) . '"'
        . ' data-name="' . htmlspecialchars((string) $promo['ten_banh'], ENT_QUOTES) . '"'
        . ' title="Sửa khuyến mãi"><i class="bi bi-pencil"></i></button> '
        . '<button type="button" class="btn btn-danger" data-delete-url="' . htmlspecialchars($deleteUrl) . '"'
        . ' data-confirm-text="' . htmlspecialchars((string) $promo['ten_banh'], ENT_QUOTES) . ' sẽ bị xóa vĩnh viễn và không thể khôi phục."'
        . ' title="Xóa khuyến mãi"><i class="bi bi-trash"></i></button>'
        . '</td>'
        . '</tr>';
}

if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:22px;">Chưa có chương trình khuyến mãi nào.</td></tr>';
}

$firstProductPrice = !empty($products) ? number_format((float) $products[0]['gia'], 0, ',', '.') . ' VNĐ' : '0 VNĐ';
?>
<div class="card panel" style="margin-bottom:18px;">
  <div class="panel-head"><h2>Thêm Khuyến Mãi</h2></div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="tab" value="promotions">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end;">
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Sản phẩm</label>
        <select name="banh_id" id="promotionProductSelect" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
          <?= $productOptionsHtml ?>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giá hiện tại</label>
        <input type="text" id="promotionCurrentPrice" value="<?= htmlspecialchars($firstProductPrice) ?>" readonly style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--muted);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giá KM</label>
        <input type="number" name="gia_khuyen_mai" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Bắt đầu</label>
        <input type="date" name="ngay_bat_dau" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Kết thúc</label>
        <input type="date" name="ngay_ket_thuc" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <button type="submit" name="add_promotion" class="btn btn-primary" title="Thêm khuyến mãi"><i class="bi bi-plus-lg"></i></button>
    </div>
  </form>
</div>

<div class="card panel">
  <div class="panel-head"><h2>Chương Trình Khuyến Mãi</h2></div>
  <?php render_table(['Sản phẩm', 'Giá hiện tại', 'Giá KM', 'Thời gian', 'Hành động'], $rowsHtml); ?>
</div>

<?php
$editPromotionBody = '<form method="POST" id="editPromotionForm" style="display:grid;gap:14px;">'
    . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
    . '<input type="hidden" name="tab" value="promotions">'
    . '<input type="hidden" name="promotion_id" id="editPromoId">'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Sản phẩm</label>'
    . '<input type="text" id="editPromoName" readonly disabled style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--muted);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giá khuyến mãi (VNĐ)</label>'
    . '<input type="number" name="gia_khuyen_mai" id="editPromoPrice" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày bắt đầu</label>'
    . '<input type="date" name="ngay_bat_dau" id="editPromoStart" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '<div><label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Ngày kết thúc</label>'
    . '<input type="date" name="ngay_ket_thuc" id="editPromoEnd" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></div>'
    . '</div>'
    . '</form>';
$editPromotionActions = '<button type="button" class="btn btn-ghost" data-modal-close>Hủy</button>'
    . '<button type="submit" form="editPromotionForm" name="update_promotion" class="btn btn-primary"><i class="bi bi-save"></i> Lưu thay đổi</button>';
render_modal('editPromotionModal', 'Chỉnh sửa khuyến mãi', $editPromotionBody, $editPromotionActions);
?>

<script>
(function () {
  var promotionProductSelect = document.getElementById('promotionProductSelect');
  var promotionCurrentPrice = document.getElementById('promotionCurrentPrice');

  function formatVnd(value) {
    var amount = Number(value || 0);
    return amount.toLocaleString('vi-VN') + ' VNĐ';
  }

  function syncPromotionCurrentPrice() {
    if (!promotionProductSelect || !promotionCurrentPrice) { return; }
    var selectedOption = promotionProductSelect.options[promotionProductSelect.selectedIndex];
    var selectedPrice = selectedOption ? selectedOption.dataset.price : 0;
    promotionCurrentPrice.value = formatVnd(selectedPrice);
  }

  if (promotionProductSelect && promotionCurrentPrice) {
    promotionProductSelect.addEventListener('change', syncPromotionCurrentPrice);
    syncPromotionCurrentPrice();
  }

  document.querySelectorAll('.promo-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('editPromoId').value = btn.dataset.id || '';
      document.getElementById('editPromoName').value = btn.dataset.name || '';
      document.getElementById('editPromoPrice').value = btn.dataset.price || '';
      document.getElementById('editPromoStart').value = btn.dataset.start || '';
      document.getElementById('editPromoEnd').value = btn.dataset.end || '';
    });
  });
})();
</script>
