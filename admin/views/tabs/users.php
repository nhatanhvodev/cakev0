<?php
/* admin/views/tabs/users.php — expects $conn (mysqli), $tab in scope (see admin/views/layout.php) */
require_once __DIR__ . '/../components/data_table.php';
require_once __DIR__ . '/../components/modal.php';

// =====================================================================
// Data — ported verbatim from admin/admin.php:
//   L949-955   users list query (u.*, total_spent via LEFT JOIN orders,
//              only paid/approved/delivered/completed orders count)
//   L959       orders list query (o.*, u.username, u.email; LEFT JOIN users)
//   L961-986   $ordersByUser / $ordersById shape, used client-side by the
//              userOrdersModal (ported from admin.php L3629-3753)
//   L988-997   order_items query + $orderItemsById shape
// Columns used: users.id/username/email/created_at (schema:
// database/banh_store.sql); orders.id/user_id/recipient_name/phone/address/
// note/total_amount/status/created_at/payment_method; order_items.order_id/
// banh_id/quantity/price; banh.ten_banh.
// =====================================================================
$users = $conn->query(
    "SELECT u.*, COALESCE(SUM(CASE WHEN o.status IN ('paid','approved','delivered','completed') THEN o.total_amount ELSE 0 END), 0) AS total_spent
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$orders = $conn->query(
    "SELECT o.*, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$order_items = $conn->query(
    "SELECT oi.*, b.ten_banh FROM order_items oi LEFT JOIN banh b ON oi.banh_id = b.id"
)->fetch_all(MYSQLI_ASSOC);

$ordersByUser = [];
$ordersById = [];
foreach ($orders as $order) {
    if (empty($order['user_id'])) {
        continue;
    }
    $uid = (int) $order['user_id'];
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
    $ordersByUser[$uid][] = [
        'id' => $orderId,
        'total_amount' => (float) $order['total_amount'],
        'status' => (string) $order['status'],
        'created_at' => (string) $order['created_at'],
    ];
}

$orderItemsById = [];
foreach ($order_items as $item) {
    $oid = (int) $item['order_id'];
    $orderItemsById[$oid][] = [
        'ten_banh' => (string) $item['ten_banh'],
        'quantity' => (int) $item['quantity'],
        'price' => (float) $item['price'],
    ];
}

$rowsHtml = '';
foreach ($users as $u) {
    $userId = (int) $u['id'];
    $deleteUrl = '?tab=users&delete_user_id=' . $userId . '&csrf=' . urlencode($_SESSION['csrf_token']);

    $rowsHtml .= '<tr>'
        . '<td>' . $userId . '</td>'
        . '<td>' . htmlspecialchars((string) $u['username']) . '</td>'
        . '<td>' . htmlspecialchars((string) $u['email']) . '</td>'
        . '<td style="font-weight:700;">' . number_format((float) $u['total_spent'], 0, ',', '.') . ' VNĐ</td>'
        . '<td>' . date('d/m/Y H:i', strtotime((string) $u['created_at'])) . '</td>'
        . '<td>'
        . '<button type="button" class="btn btn-ghost" data-modal-open="userOrdersModal" data-user-id="' . $userId . '" data-user-name="' . htmlspecialchars((string) $u['username'], ENT_QUOTES) . '" title="Đơn hàng của khách"><i class="bi bi-receipt"></i></button> '
        . '<button type="button" class="btn btn-danger" data-delete-url="' . htmlspecialchars($deleteUrl) . '" data-confirm-text="Khách hàng sẽ bị xóa vĩnh viễn và không thể khôi phục." title="Xóa khách hàng"><i class="bi bi-trash"></i></button>'
        . '</td>'
        . '</tr>';
}
?>
<div class="card panel">
  <div class="panel-head"><h2>Khách Hàng</h2></div>
  <?php render_table(['ID', 'Tên đăng nhập', 'Email', 'Tổng chi tiêu', 'Ngày đăng ký', 'Hành động'], $rowsHtml); ?>
</div>

<?php
$userOrdersBodyHtml = '<p id="userOrdersDesc" style="color:var(--muted);margin:0 0 12px;"></p>'
    . '<div class="user-orders-layout">'
    . '<div id="userOrdersBody"></div>'
    . '<div class="user-orders-detail" id="userOrdersDetail"><h6>Chi tiết đơn hàng</h6><p class="user-orders-empty">Chọn một đơn để xem chi tiết.</p></div>'
    . '</div>';
$userOrdersActions = '<button type="button" class="btn btn-ghost" data-modal-close>Đóng</button>';
render_modal('userOrdersModal', 'Đơn hàng của khách', $userOrdersBodyHtml, $userOrdersActions);
?>
<script>
(function () {
  // Client-side render for #userOrdersModal — ported from admin.php
  // renderUserOrders()/renderOrderDetail()/formatStatus() (L3629-3753).
  // Bound directly to the trigger buttons (target phase runs before
  // modals.js's delegated document listener adds .is-open), same pattern
  // as views/tabs/orders.php's adminOrderModal wiring.
  var ordersByUser = <?= json_encode($ordersByUser) ?>;
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
    return map[key] || 'Không rõ';
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

  function renderOrderDetail(orderId) {
    var detailEl = document.getElementById('userOrdersDetail');
    var detail = ordersById[orderId];
    if (!detail) {
      detailEl.innerHTML = '<h6>Chi tiết đơn hàng</h6><p class="user-orders-empty">Không có dữ liệu đơn hàng.</p>';
      return;
    }
    var items = orderItemsById[orderId] || [];
    var itemsHtml = '';
    if (items.length === 0) {
      itemsHtml = '<p class="user-orders-empty">Không có sản phẩm.</p>';
    } else {
      itemsHtml = items.map(function (item) {
        var total = Number(item.price) * Number(item.quantity);
        return '<div><span>' + escapeHtml(item.ten_banh) + ' x' + escapeHtml(item.quantity) + '</span><strong>' + total.toLocaleString('vi-VN') + 'đ</strong></div>';
      }).join('');
    }
    detailEl.innerHTML =
      '<h6>Chi tiết đơn #' + escapeHtml(detail.id) + '</h6>' +
      '<div class="user-orders-meta">' +
      '<div><strong>Người nhận:</strong> ' + escapeHtml(detail.recipient_name) + '</div>' +
      '<div><strong>SĐT:</strong> ' + escapeHtml(detail.phone) + '</div>' +
      '<div><strong>Địa chỉ:</strong> ' + escapeHtml(detail.address) + '</div>' +
      (detail.note ? '<div><strong>Ghi chú:</strong> ' + escapeHtml(detail.note) + '</div>' : '') +
      '<div><strong>Phương thức:</strong> ' + escapeHtml(detail.payment_method) + '</div>' +
      '<div><strong>Trạng thái:</strong> ' + escapeHtml(formatStatus(detail.status)) + '</div>' +
      '<div><strong>Ngày đặt:</strong> ' + escapeHtml(new Date(detail.created_at).toLocaleString('vi-VN')) + '</div>' +
      '<div><strong>Tổng tiền:</strong> ' + Number(detail.total_amount).toLocaleString('vi-VN') + 'đ</div>' +
      '</div>' +
      '<div class="user-orders-items">' + itemsHtml + '</div>';
    detailEl.scrollTop = 0;
  }

  function renderUserOrders(orders) {
    var bodyEl = document.getElementById('userOrdersBody');
    if (!orders || orders.length === 0) {
      bodyEl.innerHTML = '<p class="user-orders-empty">Khách hàng chưa có đơn hàng.</p>';
      return;
    }
    var html = '<table class="user-orders-table"><thead><tr>'
      + '<th>Mã ĐH</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th><th></th>'
      + '</tr></thead><tbody>';
    orders.forEach(function (order) {
      var dateText = new Date(order.created_at).toLocaleString('vi-VN');
      html += '<tr>'
        + '<td>#' + escapeHtml(order.id) + '</td>'
        + '<td>' + escapeHtml(dateText) + '</td>'
        + '<td>' + Number(order.total_amount).toLocaleString('vi-VN') + 'đ</td>'
        + '<td>' + escapeHtml(formatStatus(order.status)) + '</td>'
        + '<td><button type="button" class="btn btn-ghost user-order-detail-btn" data-order-id="' + escapeHtml(order.id) + '">Chi tiết</button></td>'
        + '</tr>';
    });
    html += '</tbody></table>';
    bodyEl.innerHTML = html;
    bodyEl.querySelectorAll('.user-order-detail-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        renderOrderDetail(btn.dataset.orderId);
      });
    });
  }

  document.querySelectorAll('[data-modal-open="userOrdersModal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var userId = btn.dataset.userId;
      var userName = btn.dataset.userName || 'Khách hàng';
      document.getElementById('userOrdersDesc').textContent = 'Danh sách đơn hàng của ' + userName + '.';
      document.getElementById('userOrdersDetail').innerHTML = '<h6>Chi tiết đơn hàng</h6><p class="user-orders-empty">Chọn một đơn để xem chi tiết.</p>';
      renderUserOrders(ordersByUser[userId] || []);
    });
  });
})();
</script>
