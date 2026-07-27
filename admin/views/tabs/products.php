<?php
/* admin/views/tabs/products.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../../lib/images.php';

// =====================================================================
// Data — ported verbatim from admin/admin.php:
//   L948       products list query (SELECT * FROM banh ORDER BY id DESC)
//   L1019-1024 product_images query + $productImageMap (grouped by product_id)
//   L2238-2365 products tab markup: add/edit form (single form, JS toggles
//              add/update buttons — legacy has no separate "edit modal"),
//              search box, and the products table (thumbnail via
//              buildImageUrl(), name, loại, price, description flag,
//              gallery thumbnails with per-image delete forms, edit +
//              delete-product buttons).
// Columns used: banh.id/ten_banh/slug/gia/hinh_anh/loai/mo_ta/is_featured/
//   stock/is_best_manual/best_rank (schema: database/banh_store.sql L33-46);
//   product_images.id/product_id/image_path/created_at (L285-292).
// Legacy's bespoke #deleteProductModal (admin.php L2952-2963) is superseded
// here by the shared data-delete-url confirm modal from modals.js — same
// simplification Task 0/Task 1 already established for orders' delete flow.
// =====================================================================
$products = $conn->query("SELECT * FROM banh ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$productImages = $conn->query("SELECT * FROM product_images ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$productImageMap = [];
foreach ($productImages as $imgRow) {
    $productImageMap[(int) $imgRow['product_id']][] = $imgRow;
}

if (!function_exists('admin_product_description_text')) {
    function admin_product_description_text($value): string
    {
        $text = (string) ($value ?? '');
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }

        $text = (string) preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
        $text = (string) preg_replace('/<\s*\/p\s*>/i', "\n\n", $text);
        $text = (string) preg_replace('/<\s*li\b[^>]*>/i', "- ", $text);
        $text = (string) preg_replace('/<\s*\/li\s*>/i', "\n", $text);
        $text = strip_tags($text);
        $text = (string) preg_replace('/[ \t]+\r?\n/u', "\n", $text);
        $text = (string) preg_replace('/(?:\r?\n){3,}/u', "\n\n", $text);

        return trim($text);
    }
}

$rowsHtml = '';
foreach ($products as $p) {
    $productId = (int) $p['id'];
    $isHidden = !empty($p['is_hidden']);
    $img = buildImageUrl((string) $p['hinh_anh']);
    $descriptionText = admin_product_description_text($p['mo_ta'] ?? '');

    $galleryHtml = '';
    if (!empty($productImageMap[$productId])) {
        foreach ($productImageMap[$productId] as $gallery) {
            $galleryUrl = buildImageUrl((string) $gallery['image_path']);
            $galleryHtml .= '<div style="position:relative;width:40px;height:40px;">'
                . '<img src="' . htmlspecialchars($galleryUrl['url']) . '" width="40" height="40" style="object-fit:cover;border-radius:8px;">'
                . '<form method="POST" style="position:absolute;top:-6px;right:-6px;">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">'
                . '<input type="hidden" name="tab" value="products">'
                . '<input type="hidden" name="delete_product_image" value="' . (int) $gallery['id'] . '">'
                . '<button type="submit" class="btn btn-danger" style="padding:0 4px;line-height:1;min-width:0;" title="Xóa ảnh"><i class="bi bi-x"></i></button>'
                . '</form>'
                . '</div>';
        }
        $galleryHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px;">' . $galleryHtml . '</div>';
    }

    $deleteUrl = '?tab=products&delete_product_id=' . $productId . '&csrf=' . urlencode($_SESSION['csrf_token']);
    $visibilityLabel = $isHidden ? 'Hiện sản phẩm' : 'Ẩn sản phẩm';
    $visibilityIcon = $isHidden ? 'bi-eye' : 'bi-eye-slash';
    $visibilityNextValue = $isHidden ? 0 : 1;
    $visibilityButtonClass = $isHidden ? 'btn-ghost' : 'btn-ghost product-hide-btn';

    $rowsHtml .= '<tr' . ($isHidden ? ' class="product-row-hidden"' : '') . '>'
        . '<td><img src="' . htmlspecialchars($img['url']) . '" width="50" height="50" style="object-fit:cover;border-radius:8px;"></td>'
        . '<td>' . htmlspecialchars((string) $p['ten_banh']) . '</td>'
        . '<td>' . htmlspecialchars((string) $p['loai']) . '</td>'
        . '<td style="font-weight:700;">' . number_format((float) $p['gia'], 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . ($descriptionText !== '' ? 'Có mô tả' : 'Chưa có') . '</td>'
        . '<td>' . render_badge($isHidden ? 'product-hidden' : 'product-visible') . '</td>'
        . '<td>' . $galleryHtml . '</td>'
        . '<td><div class="product-actions">'
        . '<button type="button" class="btn btn-ghost product-edit-btn"'
        . ' data-id="' . $productId . '"'
        . ' data-name="' . htmlspecialchars((string) $p['ten_banh'], ENT_QUOTES) . '"'
        . ' data-type="' . htmlspecialchars((string) $p['loai'], ENT_QUOTES) . '"'
        . ' data-price="' . (int) $p['gia'] . '"'
        . ' data-desc="' . htmlspecialchars($descriptionText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-image="' . htmlspecialchars((string) $p['hinh_anh'], ENT_QUOTES) . '"'
        . ' title="Sửa sản phẩm" aria-label="Sửa sản phẩm"><i class="bi bi-pencil"></i></button> '
        . '<form method="POST" class="product-visibility-form">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . '<input type="hidden" name="tab" value="products">'
        . '<input type="hidden" name="product_id" value="' . $productId . '">'
        . '<input type="hidden" name="is_hidden" value="' . $visibilityNextValue . '">'
        . '<button type="submit" name="toggle_product_visibility" class="btn ' . $visibilityButtonClass . '" title="' . $visibilityLabel . '" aria-label="' . $visibilityLabel . '"><i class="bi ' . $visibilityIcon . '"></i></button>'
        . '</form>'
        . '<button type="button" class="btn btn-danger" data-delete-url="' . htmlspecialchars($deleteUrl) . '" data-confirm-text="Sản phẩm sẽ bị xóa vĩnh viễn và không thể khôi phục." title="Xóa sản phẩm"><i class="bi bi-trash"></i></button>'
        . '</div></td>'
        . '</tr>';
}
?>
<div class="card panel" style="margin-bottom:18px;">
  <div class="panel-head"><h2>Thêm / Sửa Sản Phẩm</h2></div>
  <form id="productForm" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="tab" value="products">
    <input type="hidden" name="product_id" id="productId" value="">
    <input type="hidden" name="current_image" id="currentImage" value="">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Tên bánh</label>
        <input type="text" name="ten_banh" id="productName" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Loại</label>
        <select name="loai" id="productType" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
          <option value="ngot">Bánh ngọt</option>
          <option value="man">Bánh mặn</option>
          <option value="kem">Bánh kem</option>
          <option value="mi">Bánh mì</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Giá (VNĐ)</label>
        <input type="number" name="gia" id="productPrice" required style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
      </div>
      <div>
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Hình ảnh (ảnh đầu là ảnh chính)</label>
        <input type="file" name="product_images[]" multiple style="width:100%;">
        <small style="color:var(--muted);">Bỏ trống nếu không đổi ảnh khi cập nhật.</small>
      </div>
      <div style="grid-column:1 / -1;">
        <label style="display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;">Mô tả</label>
        <textarea name="mo_ta" id="productDesc" rows="4" placeholder="Mô tả ngắn về sản phẩm" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);"></textarea>
      </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;">
      <button type="submit" name="add_product" id="addProductBtn" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Thêm sản phẩm
      </button>
      <button type="submit" name="update_product" id="updateProductBtn" class="btn btn-primary" style="display:none;">
        <i class="bi bi-save"></i> Lưu cập nhật
      </button>
      <button type="button" id="cancelEditBtn" class="btn btn-ghost" style="display:none;">
        <i class="bi bi-x-circle"></i> Hủy sửa
      </button>
    </div>
  </form>
</div>

<div class="card panel">
  <div class="panel-head">
    <h2>Danh Sách Sản Phẩm</h2>
    <input type="text" id="productSearchInput" placeholder="Tìm theo tên, loại, giá..." style="max-width:280px;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text);">
  </div>
  <?php render_table(['Ảnh', 'Tên', 'Loại', 'Giá', 'Mô tả', 'Trạng thái', 'Gallery', 'Hành động'], $rowsHtml); ?>
</div>

<script>
(function () {
  // Add/edit form toggle + gallery search — ported from admin.php inline
  // script (product-edit-btn dataset population, cancel resets to "add"
  // mode, productSearchInput filters table rows client-side).
  var form = document.getElementById('productForm');
  var productId = document.getElementById('productId');
  var currentImage = document.getElementById('currentImage');
  var nameInput = document.getElementById('productName');
  var typeInput = document.getElementById('productType');
  var priceInput = document.getElementById('productPrice');
  var descInput = document.getElementById('productDesc');
  var addBtn = document.getElementById('addProductBtn');
  var updateBtn = document.getElementById('updateProductBtn');
  var cancelBtn = document.getElementById('cancelEditBtn');
  var searchInput = document.getElementById('productSearchInput');

  function resetForm() {
    form.reset();
    productId.value = '';
    currentImage.value = '';
    addBtn.style.display = '';
    updateBtn.style.display = 'none';
    cancelBtn.style.display = 'none';
  }

  document.querySelectorAll('.product-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      productId.value = btn.dataset.id || '';
      currentImage.value = btn.dataset.image || '';
      nameInput.value = btn.dataset.name || '';
      typeInput.value = btn.dataset.type || 'ngot';
      priceInput.value = btn.dataset.price || '';
      descInput.value = btn.dataset.desc || '';
      addBtn.style.display = 'none';
      updateBtn.style.display = '';
      cancelBtn.style.display = '';
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  cancelBtn.addEventListener('click', resetForm);

  var tableBody = document.querySelector('.table-scroll tbody');
  if (searchInput && tableBody) {
    var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr'));
    searchInput.addEventListener('input', function () {
      var keyword = (searchInput.value || '').trim().toLowerCase();
      rows.forEach(function (row) {
        if (keyword === '') { row.style.display = ''; return; }
        var text = (row.textContent || '').toLowerCase();
        row.style.display = text.indexOf(keyword) !== -1 ? '' : 'none';
      });
    });
  }
})();
</script>
