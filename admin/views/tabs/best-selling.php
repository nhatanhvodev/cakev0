<?php
/* admin/views/tabs/best-selling.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../../lib/images.php';

// =====================================================================
// Data — ported verbatim from admin/admin.php:
//   L948       products list query (SELECT * FROM banh ORDER BY id DESC)
//   L1026-1037 best-selling sales query (SUM(oi.quantity) per banh_id,
//              only paid/approved/delivered/completed orders) + $bestSalesMap
//   L2408-2554 best-selling tab markup: manual-selection form (product_ids[],
//              manual_best[id] checkbox, best_rank[id] number input), counts
//              (Đã chọn / Tổng SP / Có lượt bán), search box, and the
//              checkbox->rank-input enable/disable + live count JS
//              (admin.php L3944-3982, 4073-4088, minus the card sort/filter
//              controls which this table layout doesn't carry over).
// Columns used: banh.id/ten_banh/hinh_anh/loai/is_best_manual/best_rank
//   (schema: database/banh_store.sql L33-46); order_items.banh_id/quantity/
//   order_id (L191-202 area); orders.id/status.
// =====================================================================
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$bestSalesRows = $conn->query(
    "SELECT oi.banh_id, SUM(oi.quantity) AS sold_qty
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status IN ('paid','approved','delivered','completed')
     GROUP BY oi.banh_id"
)->fetch_all(MYSQLI_ASSOC);

$bestSalesMap = [];
foreach ($bestSalesRows as $row) {
    $bestSalesMap[(int) $row['banh_id']] = (int) $row['sold_qty'];
}

$manualSelectedCount = 0;
$topSoldProductCount = 0;
foreach ($products as $bestProduct) {
    if (!empty($bestProduct['is_best_manual'])) {
        $manualSelectedCount++;
    }
    $bestSoldQty = (int) ($bestSalesMap[(int) $bestProduct['id']] ?? 0);
    if ($bestSoldQty > 0) {
        $topSoldProductCount++;
    }
}

$rowsHtml = '';
foreach ($products as $p) {
    $productId = (int) $p['id'];
    $img = buildImageUrl((string) $p['hinh_anh']);
    $soldQty = (int) ($bestSalesMap[$productId] ?? 0);
    $isSelected = !empty($p['is_best_manual']);
    $rank = (int) ($p['best_rank'] ?? 0);
    $typeLabel = match ((string) ($p['loai'] ?? '')) {
        'kem' => 'Bánh kem',
        'ngot' => 'Bánh ngọt',
        'man' => 'Bánh mặn',
        'mi' => 'Bánh mì',
        default => ucfirst((string) ($p['loai'] ?? 'Khác')),
    };

    $rowsHtml .= '<tr class="best-selling-row" data-product-name="'
        . htmlspecialchars(mb_strtolower((string) $p['ten_banh'], 'UTF-8'), ENT_QUOTES) . '">'
        . '<td><img src="' . htmlspecialchars($img['url']) . '" width="44" height="44" style="object-fit:cover;border-radius:8px;"></td>'
        . '<td><input type="hidden" name="product_ids[]" value="' . $productId . '">'
        . '<strong>' . htmlspecialchars((string) $p['ten_banh']) . '</strong><br>'
        . '<span style="color:var(--muted);font-size:12px;">' . htmlspecialchars($typeLabel) . '</span></td>'
        . '<td style="font-weight:700;">' . $soldQty . '</td>'
        . '<td><input type="checkbox" class="best-selling-toggle" name="manual_best[' . $productId . ']" data-best-toggle'
        . ($isSelected ? ' checked' : '') . '></td>'
        . '<td><input type="number" min="0" class="best-selling-rank-input" name="best_rank[' . $productId . ']"'
        . ' value="' . $rank . '"' . ($isSelected ? '' : ' disabled')
        . ' style="width:90px;padding:6px 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></td>'
        . '</tr>';
}
?>
<div class="card panel best-selling-panel">
  <div class="panel-head"><h2>Best Selling</h2></div>
  <p style="color:var(--muted);margin:0 0 16px;max-width:760px;">Tick những bánh bạn muốn ưu tiên hiển thị ở mục Best Selling ngoài trang chủ. Số thứ tự càng nhỏ thì ưu tiên càng cao. Để trống hoặc để <strong>0</strong> nếu bạn chỉ muốn đánh dấu mà không ép thứ tự.</p>

  <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Đã chọn</span></div>
      <div class="val num" id="bestSellingSelectedCount"><?= $manualSelectedCount ?></div>
    </div>
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Tổng SP</span></div>
      <div class="val num"><?= count($products) ?></div>
    </div>
    <div class="card kpi" style="min-width:132px;">
      <div class="top"><span class="label">Có lượt bán</span></div>
      <div class="val num"><?= $topSoldProductCount ?></div>
    </div>
  </div>

  <form method="POST" id="bestSellingForm" class="best-selling-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="tab" value="best-selling">

    <input type="text" id="bestSellingSearch" placeholder="Nhập tên bánh để lọc nhanh" style="max-width:280px;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);margin-bottom:12px;">

    <div class="best-selling-table-shell">
      <?php render_table(['Ảnh', 'Sản phẩm', 'Đã bán', 'Chọn thủ công', 'Thứ tự ưu tiên'], $rowsHtml); ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px;">
      <div style="color:var(--muted);font-size:13px;">Bạn có thể chọn nhiều sản phẩm, nhưng nên ưu tiên một nhóm ngắn để ngoài trang chủ gọn và rõ.</div>
      <button type="submit" name="update_best_selling" class="btn btn-primary">
        <i class="bi bi-check2-circle"></i> Cập nhật Best Selling
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  // Checkbox <-> rank-input enable/disable + live "Đã chọn" count + name
  // search filter — ported from admin.php's best-selling script block
  // (L3944-3982 syncBestSellingUi, L3990-4088 wiring). The legacy card
  // sort/filter-by-status controls are not carried over: this table layout
  // only needed the search box (same simplification pattern as
  // views/tabs/products.php's productSearchInput).
  var rows = document.querySelectorAll('.best-selling-row');
  var selectedCountEl = document.getElementById('bestSellingSelectedCount');
  var searchInput = document.getElementById('bestSellingSearch');

  function syncBestSellingUi() {
    var selected = 0;
    rows.forEach(function (row) {
      var checkbox = row.querySelector('[data-best-toggle]');
      var rankInput = row.querySelector('.best-selling-rank-input');
      if (!checkbox || !rankInput) { return; }
      var active = checkbox.checked;
      rankInput.disabled = !active;
      if (!active) {
        rankInput.value = '0';
      } else {
        selected += 1;
      }
    });
    if (selectedCountEl) {
      selectedCountEl.textContent = String(selected);
    }
  }

  rows.forEach(function (row) {
    var checkbox = row.querySelector('[data-best-toggle]');
    if (checkbox) {
      checkbox.addEventListener('change', syncBestSellingUi);
    }
  });

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var keyword = (searchInput.value || '').trim().toLowerCase();
      rows.forEach(function (row) {
        if (keyword === '') { row.style.display = ''; return; }
        var name = row.dataset.productName || '';
        row.style.display = name.indexOf(keyword) !== -1 ? '' : 'none';
      });
    });
  }

  syncBestSellingUi();
})();
</script>
