<?php

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('admin_revenue_statuses')) {
    function admin_revenue_statuses(): array
    {
        return ['paid', 'approved', 'delivered', 'completed'];
    }
}

if (!function_exists('admin_order_status_label')) {
    function admin_order_status_label(string $status): string
    {
        $map = [
            'pending' => 'Chờ xác nhận',
            'paid' => 'Đã thanh toán',
            'approved' => 'Đã xác nhận',
            'confirmed' => 'Đã xác nhận',
            'delivering' => 'Đang giao',
            'delivered' => 'Đã giao',
            'completed' => 'Hoàn tất',
            'failed' => 'Thanh toán lỗi',
            'cancelled' => 'Đã hủy',
            'cod_not_deposited' => 'Chờ xác nhận COD',
            'cod_deposited' => 'COD đã xác nhận',
        ];
        $key = strtolower(trim($status));
        return $map[$key] ?? 'Không rõ';
    }
}

if (!function_exists('admin_revenue_params')) {
    function admin_revenue_params(array $source = null): array
    {
        $source = $source ?? $_GET;
        $view = strtolower((string) ($source['chart_view'] ?? 'week'));
        if ($view === '7days') {
            $view = 'week';
        }
        if (!in_array($view, ['week', 'month', 'year'], true)) {
            $view = 'week';
        }

        $currentYear = (int) date('Y');
        $year = isset($source['year']) ? (int) $source['year'] : $currentYear;
        $month = isset($source['month']) ? (int) $source['month'] : (int) date('m');
        if ($year < 2000 || $year > $currentYear + 1) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('m');
        }

        return ['chart_view' => $view, 'year' => $year, 'month' => $month];
    }
}

if (!function_exists('admin_revenue_vnd')) {
    function admin_revenue_vnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' VNĐ';
    }
}

if (!function_exists('admin_revenue_bucket_template')) {
    function admin_revenue_bucket_template(array $params): array
    {
        $buckets = [];
        $title = 'Doanh thu 7 ngày gần nhất';
        $subtitle = 'Theo ngày';

        if ($params['chart_view'] === 'month') {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-01', $params['year'], $params['month']));
            $days = (int) $date->format('t');
            for ($day = 1; $day <= $days; $day++) {
                $key = sprintf('%04d-%02d-%02d', $params['year'], $params['month'], $day);
                $buckets[$key] = [
                    'key' => $key,
                    'label' => str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                    'revenue' => 0.0,
                    'order_count' => 0,
                ];
            }
            $title = "Doanh thu tháng {$params['month']}/{$params['year']}";
            $subtitle = 'Theo ngày trong tháng';
        } elseif ($params['chart_view'] === 'year') {
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%04d-%02d', $params['year'], $month);
                $buckets[$key] = [
                    'key' => $key,
                    'label' => 'T' . $month,
                    'revenue' => 0.0,
                    'order_count' => 0,
                ];
            }
            $title = "Doanh thu năm {$params['year']}";
            $subtitle = 'Theo tháng';
        } else {
            for ($i = 6; $i >= 0; $i--) {
                $date = new DateTimeImmutable("-{$i} days");
                $key = $date->format('Y-m-d');
                $buckets[$key] = [
                    'key' => $key,
                    'label' => $date->format('d/m'),
                    'revenue' => 0.0,
                    'order_count' => 0,
                ];
            }
        }

        return [$buckets, $title, $subtitle];
    }
}

if (!function_exists('admin_revenue_bucket_key')) {
    function admin_revenue_bucket_key(string $createdAt, array $params): ?string
    {
        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            return null;
        }
        if ($params['chart_view'] === 'month') {
            return date('Y-m-d', $timestamp);
        }
        if ($params['chart_view'] === 'year') {
            return date('Y-m', $timestamp);
        }
        return date('Y-m-d', $timestamp);
    }
}

if (!function_exists('admin_revenue_report')) {
    function admin_revenue_report(mysqli $conn, array $params = null): array
    {
        $params = $params ?? admin_revenue_params();
        [$buckets, $title, $subtitle] = admin_revenue_bucket_template($params);
        $revenueStatuses = admin_revenue_statuses();

        $orders = $conn->query(
            "SELECT o.*, u.username, u.email
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC"
        )->fetch_all(MYSQLI_ASSOC);

        $totalRevenue = 0.0;
        $revenueOrderCount = 0;
        $pendingCount = 0;
        $statusMap = [];

        foreach ($orders as $order) {
            $status = strtolower((string) ($order['status'] ?? ''));
            $amount = (float) ($order['total_amount'] ?? 0);
            if (!isset($statusMap[$status])) {
                $statusMap[$status] = [
                    'status' => $status,
                    'label' => admin_order_status_label($status),
                    'order_count' => 0,
                    'revenue' => 0.0,
                ];
            }
            $statusMap[$status]['order_count']++;

            if (in_array($status, ['pending', 'cod_not_deposited'], true)) {
                $pendingCount++;
            }

            if (!in_array($status, $revenueStatuses, true)) {
                continue;
            }

            $totalRevenue += $amount;
            $revenueOrderCount++;
            $statusMap[$status]['revenue'] += $amount;

            $bucketKey = admin_revenue_bucket_key((string) $order['created_at'], $params);
            if ($bucketKey !== null && isset($buckets[$bucketKey])) {
                $buckets[$bucketKey]['revenue'] += $amount;
                $buckets[$bucketKey]['order_count']++;
            }
        }

        $topProducts = $conn->query(
            "SELECT b.ten_banh, SUM(oi.quantity) AS sold_qty, SUM(oi.quantity * oi.price) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN banh b ON b.id = oi.banh_id
             WHERE b.is_hidden = 0 AND LOWER(o.status) IN ('paid','approved','delivered','completed')
             GROUP BY oi.banh_id, b.ten_banh
             ORDER BY sold_qty DESC, revenue DESC
             LIMIT 8"
        )->fetch_all(MYSQLI_ASSOC);

        $newUsers = (int) ($conn->query(
            "SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetch_assoc()['c'] ?? 0);

        usort($statusMap, static function (array $a, array $b): int {
            return $b['order_count'] <=> $a['order_count'];
        });

        return [
            'params' => $params,
            'title' => $title,
            'subtitle' => $subtitle,
            'rows' => array_values($buckets),
            'labels' => array_column($buckets, 'label'),
            'values' => array_map('floatval', array_column($buckets, 'revenue')),
            'order_counts' => array_map('intval', array_column($buckets, 'order_count')),
            'total_revenue' => $totalRevenue,
            'revenue_order_count' => $revenueOrderCount,
            'avg_order_value' => $revenueOrderCount > 0 ? $totalRevenue / $revenueOrderCount : 0.0,
            'total_order_count' => count($orders),
            'pending_count' => $pendingCount,
            'new_users' => $newUsers,
            'top_products' => $topProducts,
            'status_breakdown' => array_values($statusMap),
            'recent_orders' => array_slice($orders, 0, 8),
        ];
    }
}

if (!function_exists('admin_revenue_export_url')) {
    function admin_revenue_export_url(string $format, array $params): string
    {
        return 'index.php?' . http_build_query([
            'tab' => 'dashboard',
            'chart_view' => $params['chart_view'],
            'month' => $params['month'],
            'year' => $params['year'],
            'export_revenue' => 1,
            'export_format' => $format,
        ]);
    }
}

if (!function_exists('admin_revenue_filename')) {
    function admin_revenue_filename(array $params, string $format): string
    {
        $suffix = $params['chart_view'] . '_' . $params['year'];
        if ($params['chart_view'] === 'month') {
            $suffix .= '_' . str_pad((string) $params['month'], 2, '0', STR_PAD_LEFT);
        } elseif ($params['chart_view'] === 'week') {
            $suffix .= '_' . date('Ymd');
        }
        return "revenue_report_{$suffix}.{$format}";
    }
}

if (!function_exists('admin_revenue_csv')) {
    function admin_revenue_csv(array $report): void
    {
        $filename = admin_revenue_filename($report['params'], 'csv');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Báo cáo doanh thu', $report['title']]);
        fputcsv($out, ['Tổng doanh thu', admin_revenue_vnd((float) $report['total_revenue'])]);
        fputcsv($out, ['Số đơn tính doanh thu', (int) $report['revenue_order_count']]);
        fputcsv($out, ['Giá trị đơn trung bình', admin_revenue_vnd((float) $report['avg_order_value'])]);
        fputcsv($out, []);
        fputcsv($out, ['Kỳ', 'Doanh thu (VNĐ)', 'Số đơn']);
        foreach ($report['rows'] as $row) {
            fputcsv($out, [$row['label'], (float) $row['revenue'], (int) $row['order_count']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Sản phẩm bán chạy', 'Số lượng', 'Doanh thu (VNĐ)']);
        foreach ($report['top_products'] as $row) {
            fputcsv($out, [(string) $row['ten_banh'], (int) $row['sold_qty'], (float) $row['revenue']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Trạng thái đơn', 'Số đơn', 'Doanh thu (VNĐ)']);
        foreach ($report['status_breakdown'] as $row) {
            fputcsv($out, [$row['label'], (int) $row['order_count'], (float) $row['revenue']]);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('admin_revenue_xlsx')) {
    function admin_revenue_xlsx(array $report): void
    {
        if (!class_exists(Spreadsheet::class)) {
            http_response_code(500);
            echo 'Chưa cài đặt thư viện PhpSpreadsheet.';
            exit;
        }

        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Tổng quan');
        $summary->fromArray([
            ['Báo cáo doanh thu', $report['title']],
            ['Ngày xuất', date('d/m/Y H:i')],
            ['Tổng doanh thu', (float) $report['total_revenue']],
            ['Số đơn tính doanh thu', (int) $report['revenue_order_count']],
            ['Giá trị đơn trung bình', (float) $report['avg_order_value']],
            ['Tổng số đơn', (int) $report['total_order_count']],
            ['Đơn chờ xử lý', (int) $report['pending_count']],
        ]);

        $revenue = $spreadsheet->createSheet();
        $revenue->setTitle('Doanh thu');
        $revenue->fromArray([['Kỳ', 'Doanh thu (VNĐ)', 'Số đơn']], null, 'A1');
        $row = 2;
        foreach ($report['rows'] as $item) {
            $revenue->fromArray([[$item['label'], (float) $item['revenue'], (int) $item['order_count']]], null, "A{$row}");
            $row++;
        }

        $products = $spreadsheet->createSheet();
        $products->setTitle('Sản phẩm');
        $products->fromArray([['Sản phẩm', 'Số lượng bán', 'Doanh thu (VNĐ)']], null, 'A1');
        $row = 2;
        foreach ($report['top_products'] as $item) {
            $products->fromArray([[(string) $item['ten_banh'], (int) $item['sold_qty'], (float) $item['revenue']]], null, "A{$row}");
            $row++;
        }

        $statuses = $spreadsheet->createSheet();
        $statuses->setTitle('Trạng thái');
        $statuses->fromArray([['Trạng thái', 'Số đơn', 'Doanh thu (VNĐ)']], null, 'A1');
        $row = 2;
        foreach ($report['status_breakdown'] as $item) {
            $statuses->fromArray([[$item['label'], (int) $item['order_count'], (float) $item['revenue']]], null, "A{$row}");
            $row++;
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
            $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$highestColumn}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8E8CF');
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E6DDD2');
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            foreach (range('A', $highestColumn) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $summary->getStyle('B3:B5')->getNumberFormat()->setFormatCode('#,##0');
        $revenue->getStyle('B2:B' . max(2, $revenue->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0');
        $products->getStyle('C2:C' . max(2, $products->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0');
        $statuses->getStyle('C2:C' . max(2, $statuses->getHighestRow()))->getNumberFormat()->setFormatCode('#,##0');

        $filename = admin_revenue_filename($report['params'], 'xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }
}

if (!function_exists('admin_revenue_h')) {
    function admin_revenue_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('admin_revenue_pdf')) {
    function admin_revenue_pdf(array $report): void
    {
        if (!class_exists(Dompdf::class)) {
            http_response_code(500);
            echo 'Chưa cài đặt thư viện Dompdf.';
            exit;
        }

        $rowsHtml = '';
        foreach ($report['rows'] as $row) {
            $rowsHtml .= '<tr><td>' . admin_revenue_h($row['label']) . '</td><td>' .
                admin_revenue_h(admin_revenue_vnd((float) $row['revenue'])) . '</td><td>' .
                (int) $row['order_count'] . '</td></tr>';
        }
        $productHtml = '';
        foreach ($report['top_products'] as $row) {
            $productHtml .= '<tr><td>' . admin_revenue_h($row['ten_banh']) . '</td><td>' .
                (int) $row['sold_qty'] . '</td><td>' .
                admin_revenue_h(admin_revenue_vnd((float) $row['revenue'])) . '</td></tr>';
        }
        $statusHtml = '';
        foreach ($report['status_breakdown'] as $row) {
            $statusHtml .= '<tr><td>' . admin_revenue_h($row['label']) . '</td><td>' .
                (int) $row['order_count'] . '</td><td>' .
                admin_revenue_h(admin_revenue_vnd((float) $row['revenue'])) . '</td></tr>';
        }

        $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;color:#2b2118;font-size:12px}
            h1{font-size:22px;margin:0 0 6px;color:#4a2d17} h2{font-size:15px;margin:22px 0 8px}
            .muted{color:#75685d}.grid{display:table;width:100%;margin:14px 0 18px}.card{display:table-cell;width:25%;padding:10px;border:1px solid #e6ddd2}
            .label{font-size:10px;color:#75685d;text-transform:uppercase}.value{font-size:15px;font-weight:bold;margin-top:4px}
            table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #e6ddd2;padding:7px;text-align:left}
            th{background:#f8e8cf;color:#4a2d17}.right{text-align:right}
        </style></head><body>
        <h1>Báo cáo doanh thu Gấu Bakery</h1>
        <div class="muted">' . admin_revenue_h($report['title']) . ' · Xuất lúc ' . date('d/m/Y H:i') . '</div>
        <div class="grid">
          <div class="card"><div class="label">Tổng doanh thu</div><div class="value">' . admin_revenue_h(admin_revenue_vnd((float) $report['total_revenue'])) . '</div></div>
          <div class="card"><div class="label">Đơn doanh thu</div><div class="value">' . (int) $report['revenue_order_count'] . '</div></div>
          <div class="card"><div class="label">AOV</div><div class="value">' . admin_revenue_h(admin_revenue_vnd((float) $report['avg_order_value'])) . '</div></div>
          <div class="card"><div class="label">Đơn chờ</div><div class="value">' . (int) $report['pending_count'] . '</div></div>
        </div>
        <h2>Doanh thu theo kỳ</h2><table><thead><tr><th>Kỳ</th><th>Doanh thu</th><th>Số đơn</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table>
        <h2>Sản phẩm bán chạy</h2><table><thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Doanh thu</th></tr></thead><tbody>' . ($productHtml ?: '<tr><td colspan="3">Chưa có dữ liệu</td></tr>') . '</tbody></table>
        <h2>Trạng thái đơn</h2><table><thead><tr><th>Trạng thái</th><th>Số đơn</th><th>Doanh thu</th></tr></thead><tbody>' . $statusHtml . '</tbody></table>
        </body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream(admin_revenue_filename($report['params'], 'pdf'), ['Attachment' => true]);
        exit;
    }
}

if (!function_exists('admin_revenue_export')) {
    function admin_revenue_export(mysqli $conn): void
    {
        $format = strtolower((string) ($_GET['export_format'] ?? 'xlsx'));
        if (!in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            $format = 'xlsx';
        }

        $report = admin_revenue_report($conn, admin_revenue_params($_GET));
        if ($format === 'csv') {
            admin_revenue_csv($report);
        }
        if ($format === 'pdf') {
            admin_revenue_pdf($report);
        }
        admin_revenue_xlsx($report);
    }
}
