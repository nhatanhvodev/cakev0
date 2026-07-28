<?php
/* admin/views/tabs/orders.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/stat_card.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/modal.php';
require_once __DIR__ . '/../../../includes/order_helpers.php';

// =====================================================================
// Data — ported verbatim from admin/admin.php:
//   L959       orders list query (o.*, u.username, u.email; LEFT JOIN users)
//   L988       order_items query (oi.*, b.ten_banh; LEFT JOIN banh)
//   L963-997   $ordersById / $orderItemsById shape, used client-side by the
//              adminOrderModal detail renderer (ported from admin.php L3574-3606)
//   L2164-2211 status label map + per-order status <select> options
//              (COD-only options prepended when isCodPaymentMethod() is true)
// Columns used: orders.id/user_id/recipient_name/phone/address/note/
// total_amount/status/created_at/payment_method (schema: database/banh_store.sql
// L213-228); order_items.order_id/banh_id/quantity/price (L191-202);
// users.username/email (LEFT JOIN, may be NULL — orders.user_id is NOT NULL
// in schema so this join always matches in practice, but code still guards
// with ?? 'N/A' as legacy did).
// =====================================================================
$orders = $conn->query(
    "SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$order_items = $conn->query(
    "SELECT oi.*, b.ten_banh FROM order_items oi LEFT JOIN banh b ON oi.banh_id = b.id"
)->fetch_all(MYSQLI_ASSOC);

$ordersById = [];
$orderItemsById = [];
foreach ($orders as $order) {
    $orderId = (int) $order['id'];
    $ordersById[$orderId] = [
        'id' => $orderId,
        'recipient_name' => (string) $order['recipient_name'],
        'phone' => (string) $order['phone'],
        'address' => (string) $order['address'],
        'note' => (string) ($order['note'] ?? ''),
        'payment_method' => (string) $order['payment_method'],
        'total_amount' => (float) $order['total_amount'],
        'status' => (string) $order['status'],
        'created_at' => (string) $order['created_at'],
    ];
}
foreach ($order_items as $item) {
    $oid = (int) $item['order_id'];
    $orderItemsById[$oid][] = [
        'ten_banh' => (string) $item['ten_banh'],
        'quantity' => (int) $item['quantity'],
        'price' => (float) $item['price'],
    ];
}

$statusLabelsBase = [
    'pending' => 'Đang chờ',
    'paid' => 'Đã thanh toán',
    'approved' => 'Đã xác nhận',
    'delivering' => 'Đang giao',
    'delivered' => 'Đã giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
    'failed' => 'Thanh toán lỗi',
];
$legacyCodLabels = [
    'cod_not_deposited' => 'Chờ xác nhận COD',
    'cod_deposited' => 'COD đã xác nhận',
];

$rowsHtml = '';
foreach ($orders as $o) {
    $orderId = (int) $o['id'];
    $currentStatus = strtolower((string) $o['status']);

    $statusOptions = $statusLabelsBase;
    if (isset($legacyCodLabels[$currentStatus])) {
        $statusOptions = [$currentStatus => $legacyCodLabels[$currentStatus]] + $statusOptions;
    } elseif (!isset($statusOptions[$currentStatus])) {
        $statusOptions = [$currentStatus => ucfirst((string) $o['status'])] + $statusOptions;
    }

    $itemsHtml = '';
    foreach ($order_items as $i) {
        if ((int) $i['order_id'] === $orderId) {
            $itemsHtml .= '<div>- ' . htmlspecialchars((string) $i['ten_banh']) . ' (x' . (int) $i['quantity'] . ')</div>';
        }
    }

    $optionsHtml = '';
    foreach ($statusOptions as $value => $label) {
        $selected = ($currentStatus === $value) ? ' selected' : '';
        $optionsHtml .= '<option value="' . htmlspecialchars((string) $value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }

    $deleteUrl = '?tab=orders&delete_order_id=' . $orderId . '&csrf=' . urlencode($_SESSION['csrf_token']);

    $rowsHtml .= '<tr>'
        . '<td><input type="checkbox" id="order-select-' . $orderId . '" name="selected_orders[]" value="' . $orderId . '" class="order-select" aria-label="Chọn đơn #' . $orderId . '"></td>'
        . '<td>#' . $orderId . '</td>'
        . '<td><strong>' . htmlspecialchars((string) ($o['username'] ?? 'N/A')) . '</strong><br>'
        . '<span style="color:var(--muted);font-size:12px;">' . htmlspecialchars((string) ($o['email'] ?? '')) . '</span></td>'
        . '<td>' . $itemsHtml . '</td>'
        . '<td style="font-weight:700;">' . number_format((float) $o['total_amount'], 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . render_badge((string) $o['status']) . '</td>'
        . '<td><select name="order_status[' . $orderId . ']" class="order-status-select" data-order-id="' . $orderId . '" aria-label="Trạng thái đơn #' . $orderId . '">' . $optionsHtml . '</select></td>'
        . '<td>'
        . '<button type="button" class="btn btn-ghost" data-modal-open="adminOrderModal" data-order-id="' . $orderId . '" title="Chi tiết đơn hàng"><i class="bi bi-eye"></i></button> '
        . '<button type="button" class="btn btn-danger" data-delete-url="' . htmlspecialchars($deleteUrl) . '" data-confirm-text="Đơn hàng sẽ bị xóa vĩnh viễn và không thể khôi phục." title="Xóa đơn hàng"><i class="bi bi-trash"></i></button>'
        . '</td>'
        . '</tr>';
}
?>
<div class="card panel orders-panel">
  <div class="panel-head"><h2>Quản Lý Đơn Hàng</h2></div>
  <form method="POST" id="ordersBulkForm" class="orders-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="tab" value="orders">
    <div class="orders-actionbar">
      <div class="orders-selection" aria-live="polite">
        <i class="bi bi-check2-square" aria-hidden="true"></i>
        <strong id="ordersSelectedCount">Chưa chọn đơn</strong>
      </div>
      <button type="submit" name="update_order_statuses" id="ordersBulkSubmit" class="btn btn-primary orders-bulk-button">
        <i class="bi bi-check2-circle"></i> Cập nhật trạng thái đã chọn
      </button>
    </div>
    <div class="orders-table-shell">
    <?php render_table(['', 'ID', 'Khách hàng', 'Chi tiết SP', 'Tổng tiền', 'Trạng thái', 'Cập nhật', 'Hành động'], $rowsHtml); ?>
    </div>
  </form>
</div>

<?php
$orderModalBody = '<p id="adminOrderDesc" style="color:var(--muted);margin:0 0 12px;"></p>'
    . '<div id="adminOrderDetail"></div>';
$orderModalActions = '<button type="button" class="btn btn-ghost" data-modal-close>Đóng</button>';
render_modal('adminOrderModal', 'Chi tiết đơn hàng', $orderModalBody, $orderModalActions);
?>
<script>
(function () {
  // Client-side detail render for #adminOrderModal — ported from admin.php
  // renderAdminOrderDetail()/formatStatus() (L3574-3661). Data attached here
  // instead of relying on modals.js (which only knows open/close/focus-trap),
  // so this listener is bound directly to the trigger buttons: DOM dispatch
  // runs target-phase listeners (this one) before the delegated document
  // listener in modals.js that actually adds .is-open, so content is filled
  // in before the modal becomes visible.
  var ordersById = <?= json_encode($ordersById) ?>;
  var orderItemsById = <?= json_encode($orderItemsById) ?>;

  function formatStatus(status) {
    var map = {
      pending: 'Đang chờ xác nhận', paid: 'Đã thanh toán', approved: 'Đã xác nhận',
      confirmed: 'Đã xác nhận', delivering: 'Đang giao', delivered: 'Đã giao',
      completed: 'Hoàn tất', failed: 'Thanh toán lỗi', cancelled: 'Đã hủy',
      cod_not_deposited: 'Chờ xác nhận COD', cod_deposited: 'COD đã xác nhận'
    };
    var key = (status || '').toLowerCase();
    return map[key] || status;
  }

  function renderAdminOrderDetail(orderId) {
    var detail = ordersById[orderId];
    var detailEl = document.getElementById('adminOrderDetail');
    var descEl = document.getElementById('adminOrderDesc');
    if (descEl) { descEl.textContent = 'Chi tiết đơn hàng #' + orderId + '.'; }
    if (!detail) {
      detailEl.innerHTML = '<p>Không có dữ liệu đơn hàng.</p>';
      return;
    }
    var items = orderItemsById[orderId] || [];
    var itemsHtml = '';
    if (items.length === 0) {
      itemsHtml = '<p>Không có sản phẩm.</p>';
    } else {
      itemsHtml = items.map(function (item) {
        var total = Number(item.price) * Number(item.quantity);
        return '<div style="display:flex;justify-content:space-between;padding:4px 0;">'
          + '<span>' + item.ten_banh + ' x' + item.quantity + '</span>'
          + '<strong>' + total.toLocaleString('vi-VN') + 'đ</strong></div>';
      }).join('');
    }
    detailEl.innerHTML =
      '<h4 style="margin:0 0 10px;">Đơn #' + detail.id + '</h4>' +
      '<div style="display:flex;flex-direction:column;gap:4px;font-size:13.5px;">' +
      '<div><strong>Tên người nhận:</strong> ' + detail.recipient_name + '</div>' +
      '<div><strong>SĐT:</strong> ' + detail.phone + '</div>' +
      '<div><strong>Địa chỉ:</strong> ' + detail.address + '</div>' +
      (detail.note ? '<div><strong>Ghi chú:</strong> ' + detail.note + '</div>' : '') +
      '<div><strong>Phương thức thanh toán:</strong> ' + detail.payment_method + '</div>' +
      '<div><strong>Trạng thái:</strong> ' + formatStatus(detail.status) + '</div>' +
      '<div><strong>Ngày đặt:</strong> ' + new Date(detail.created_at).toLocaleString('vi-VN') + '</div>' +
      '<div><strong>Tổng tiền:</strong> ' + Number(detail.total_amount).toLocaleString('vi-VN') + 'đ</div>' +
      '</div>' +
      '<div style="margin-top:12px;border-top:1px solid var(--border);padding-top:10px;">' + itemsHtml + '</div>';
  }

  document.querySelectorAll('[data-modal-open="adminOrderModal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      renderAdminOrderDetail(btn.dataset.orderId);
    });
  });

  var bulkForm = document.getElementById('ordersBulkForm');
  if (bulkForm) {
    var selectedCount = document.getElementById('ordersSelectedCount');
    var submitButtons = Array.prototype.slice.call(bulkForm.querySelectorAll('.orders-bulk-button'));
    var checkboxes = Array.prototype.slice.call(bulkForm.querySelectorAll('.order-select'));

    function updateSelectionState() {
      var count = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
      if (selectedCount) {
        selectedCount.textContent = count > 0 ? count + ' đơn đã chọn' : 'Chưa chọn đơn';
      }
      submitButtons.forEach(function (button) { button.disabled = count === 0; });
      checkboxes.forEach(function (checkbox) {
        var row = checkbox.closest('tr');
        if (row) {
          row.classList.toggle('is-selected', checkbox.checked);
        }
      });
    }

    checkboxes.forEach(function (checkbox) {
      checkbox.addEventListener('change', updateSelectionState);
    });

    bulkForm.querySelectorAll('.order-status-select').forEach(function (select) {
      select.addEventListener('change', function () {
        var checkbox = document.getElementById('order-select-' + select.dataset.orderId);
        if (checkbox && !checkbox.checked) {
          checkbox.checked = true;
        }
        updateSelectionState();
      });
    });

    updateSelectionState();
  }
})();
</script>
