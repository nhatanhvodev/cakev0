<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function invoice_pdf_runtime_dir(): string
{
    return APP_ROOT . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'dompdf';
}

function ensure_invoice_pdf_runtime_dir(): string
{
    $dir = invoice_pdf_runtime_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create Dompdf runtime directory: ' . $dir);
    }

    if (!is_writable($dir)) {
        throw new RuntimeException('Dompdf runtime directory is not writable: ' . $dir);
    }

    return $dir;
}

function build_invoice_pdf_options(): Options
{
    $runtimeDir = ensure_invoice_pdf_runtime_dir();

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('tempDir', $runtimeDir);
    $options->set('fontCache', $runtimeDir);
    $options->set('defaultFont', 'DejaVu Sans');

    return $options;
}

function build_invoice_filename(int $orderId): string
{
    return 'hoa-don-' . $orderId . '.pdf';
}

function format_invoice_money(float $amount): string
{
    return number_format($amount, 0, ',', '.');
}

function calculate_invoice_subtotal(array $items): float
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0));
    }

    return $subtotal;
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
    $subtotalAmount = calculate_invoice_subtotal($items);

    if (($couponCode !== '' || $couponDiscount > 0) && $couponDiscount <= 0 && $subtotalAmount > $totalAmount) {
        $couponDiscount = max(0.0, $subtotalAmount - $totalAmount);
    }

    $invoiceFilename = htmlspecialchars(build_invoice_filename($orderId), ENT_QUOTES, 'UTF-8');
    $formattedSubtotalAmount = format_invoice_money($subtotalAmount);
    $formattedTotalAmount = format_invoice_money($totalAmount);

    $rowsHtml = '';
    foreach ($items as $index => $item) {
        $name = htmlspecialchars((string) ($item['ten_banh'] ?? ''), ENT_QUOTES, 'UTF-8');
        $quantity = (int) ($item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $lineTotal = $quantity * $price;

        $rowsHtml .= sprintf(
            '<tr><td>%d</td><td>%s</td><td class="text-right">%d</td><td class="text-right">%s VN&#272;</td><td class="text-right">%s VN&#272;</td></tr>',
            $index + 1,
            $name,
            $quantity,
            format_invoice_money($price),
            format_invoice_money($lineTotal)
        );
    }

    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="5" class="empty">Kh&#244;ng c&#243; s&#7843;n ph&#7849;m.</td></tr>';
    }

    $summaryRowsHtml = sprintf(
        '<tr><td>T&#7841;m t&#237;nh</td><td class="text-right">%s VN&#272;</td></tr>',
        $formattedSubtotalAmount
    );

    if ($couponCode !== '' || $couponDiscount > 0) {
        $couponLabel = htmlspecialchars($couponCode !== '' ? $couponCode : 'Khuy&#7871;n m&#227;i', ENT_QUOTES, 'UTF-8');
        $summaryRowsHtml .= sprintf(
            '<tr><td>M&#227; gi&#7843;m gi&#225;</td><td class="text-right">%s</td></tr><tr class="discount-row"><td>Gi&#7843;m gi&#225;</td><td class="text-right">-%s VN&#272;</td></tr>',
            $couponLabel,
            format_invoice_money($couponDiscount)
        );
    }

    $summaryRowsHtml .= sprintf(
        '<tr class="summary-total"><td>T&#7893;ng c&#7897;ng</td><td class="text-right">%s VN&#272;</td></tr>',
        $formattedTotalAmount
    );

    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>H&#243;a &#273;&#417;n #{$orderId}</title>
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
        .summary-table {
            width: 280px;
            margin-top: 16px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .summary-table .discount-row {
            color: #0a7a3d;
        }
        .summary-table .summary-total td {
            padding-top: 10px;
            border-top: 1px solid #d7d7d7;
            font-size: 15px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <h1>H&#243;a &#273;&#417;n #{$orderId}</h1>
    <div class="muted">Ng&#224;y t&#7841;o &#273;&#417;n: {$createdAt}</div>

    <div class="section">
        <table class="meta-table">
            <tr>
                <td>
                    <strong>Ng&#432;&#7901;i nh&#7853;n:</strong><br>
                    {$recipientName}<br>
                    {$phone}
                </td>
                <td>
                    <strong>&#272;&#7883;a ch&#7881; giao h&#224;ng:</strong><br>
                    {$address}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Ph&#432;&#417;ng th&#7913;c thanh to&#225;n:</strong><br>
                    {$paymentMethod}
                </td>
                <td>
                    <strong>T&#7879;p h&#243;a &#273;&#417;n:</strong><br>
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
                    <th>S&#7843;n ph&#7849;m</th>
                    <th style="width: 12%;" class="text-right">SL</th>
                    <th style="width: 20%;" class="text-right">&#272;&#417;n gi&#225;</th>
                    <th style="width: 22%;" class="text-right">Th&#224;nh ti&#7873;n</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
    </div>

    <table class="summary-table">
        {$summaryRowsHtml}
    </table>
</body>
</html>
HTML;
}

function render_invoice_pdf(array $order, array $items): string
{
    $dompdf = new Dompdf(build_invoice_pdf_options());
    $dompdf->loadHtml(render_invoice_html($order, $items), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
