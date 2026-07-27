<?php
function render_badge(string $status): string {
  $map = [
    'completed' => ['success','Hoàn thành'], 'delivered' => ['success','Đã giao'],
    'paid' => ['success','Đã thanh toán'], 'approved' => ['info','Đang xử lý'],
    'processing' => ['info','Đang xử lý'], 'pending' => ['warn','Chờ duyệt'],
    'cancelled' => ['danger','Đã huỷ'], 'canceled' => ['danger','Đã huỷ'],
    'review-approved' => ['success','Đã duyệt'], 'rejected' => ['danger','Đã từ chối'],
    'request-approved' => ['success','Đã duyệt'],
    'coupon-active' => ['success','Hoạt động'], 'coupon-inactive' => ['info','Tạm tắt'],
    'contact-pending' => ['warn','Chờ xử lý'], 'contact-replied' => ['success','Đã phản hồi'],
  ];
  $key = strtolower(trim($status));
  [$cls,$label] = $map[$key] ?? ['info', $status];
  return '<span class="pill ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}
