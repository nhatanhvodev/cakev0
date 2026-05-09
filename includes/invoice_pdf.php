<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function build_invoice_filename(int $orderId): string
{
    return 'hoa-don-' . $orderId . '.pdf';
}

function format_invoice_money(float $amount): string
{
    return number_format($amount, 0, ',', '.');
}

function render_invoice_html(array $order, array $items): string
{
    $orderId = (int) ($order['id'] ?? 0);
    $createdAt = htmlspecialchars((string) ($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
    $recipientName = htmlspecialchars((string) ($order['recipient_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars((string) ($order['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = nl2br(htmlspecialchars((string) ($order['address'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $paymentMethod = htmlspecialchars((string) ($order['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8');
    $couponCode = trim((string) ($order['coupon_code'] ?? ''));
    $couponDiscount = (float) ($order['coupon_discount'] ?? 0);
    $totalAmount = (float) ($order['total_amount'] ?? 0);
    $invoiceFilename = htmlspecialchars(build_invoice_filename($orderId), ENT_QUOTES, 'UTF-8');
    $formattedTotalAmount = format_invoice_money($totalAmount);

    $rowsHtml = '';
    foreach ($items as $index => $item) {
        $name = htmlspecialchars((string) ($item['ten_banh'] ?? ''), ENT_QUOTES, 'UTF-8');
        $quantity = (int) ($item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $lineTotal = $quantity * $price;

        $rowsHtml .= sprintf(
            '<tr><td>%d</td><td>%s</td><td class="text-right">%d</td><td class="text-right">%s VNĐ</td><td class="text-right">%s VNĐ</td></tr>',
            $index + 1,
            $name,
            $quantity,
            format_invoice_money($price),
            format_invoice_money($lineTotal)
        );
    }

    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="5" class="empty">Không có sản phẩm.</td></tr>';
    }

    $couponHtml = '';
    if ($couponCode !== '' || $couponDiscount > 0) {
        $couponLabel = htmlspecialchars($couponCode !== '' ? $couponCode : 'Khuyến mãi', ENT_QUOTES, 'UTF-8');
        $couponHtml = sprintf(
            '<div class="summary-row"><span>Mã giảm giá</span><span>%s</span></div><div class="summary-row discount"><span>Giảm giá</span><span>-%s VNĐ</span></div>',
            $couponLabel,
            format_invoice_money($couponDiscount)
        );
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn #{$orderId}</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            color: #222;
            line-height: 1.45;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        .muted {
            color: #666;
        }
        .section {
            margin-top: 18px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            width: 50%;
            vertical-align: top;
            padding: 4px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #d7d7d7;
            padding: 8px;
        }
        .items-table th {
            background: #f3f3f3;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .empty {
            text-align: center;
            color: #666;
        }
        .summary {
            margin-top: 16px;
            margin-left: auto;
            width: 280px;
        }
        .summary-row {
            display: block;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .summary-row span:first-child {
            float: left;
        }
        .summary-row span:last-child {
            float: right;
        }
        .summary-row.discount {
            color: #0a7a3d;
        }
        .summary-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #d7d7d7;
            font-size: 15px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <h1>Hóa đơn #{$orderId}</h1>
    <div class="muted">Ngày tạo đơn: {$createdAt}</div>

    <div class="section">
        <table class="meta-table">
            <tr>
                <td>
                    <strong>Người nhận:</strong><br>
                    {$recipientName}<br>
                    {$phone}
                </td>
                <td>
                    <strong>Địa chỉ giao hàng:</strong><br>
                    {$address}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Phương thức thanh toán:</strong><br>
                    {$paymentMethod}
                </td>
                <td>
                    <strong>Tệp hóa đơn:</strong><br>
                    {$invoiceFilename}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">STT</th>
                    <th>Sản phẩm</th>
                    <th style="width: 12%;" class="text-right">SL</th>
                    <th style="width: 20%;" class="text-right">Đơn giá</th>
                    <th style="width: 22%;" class="text-right">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
    </div>

    <div class="summary">
        {$couponHtml}
        <div class="summary-row summary-total">
            <span>Tổng cộng</span>
            <span>{$formattedTotalAmount} VNĐ</span>
        </div>
    </div>
</body>
</html>
HTML;
}

function render_invoice_pdf(array $order, array $items): string
{
    $options = new Options();
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(render_invoice_html($order, $items), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
