<?php

function best_selling_revenue_statuses(): array
{
    return ['paid', 'approved', 'delivered', 'completed'];
}

function best_selling_status_sql(): string
{
    return "'" . implode("','", best_selling_revenue_statuses()) . "'";
}

function best_selling_sales_map(mysqli $conn): array
{
    $statuses = best_selling_status_sql();
    $rows = $conn->query(
        "SELECT oi.banh_id, SUM(oi.quantity) AS sold_qty
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE LOWER(o.status) IN ({$statuses})
         GROUP BY oi.banh_id"
    );

    $map = [];
    if (!$rows) {
        return $map;
    }

    foreach ($rows->fetch_all(MYSQLI_ASSOC) as $row) {
        $map[(int) $row['banh_id']] = (int) $row['sold_qty'];
    }

    return $map;
}

function best_selling_append_unique(array &$list, array &$seen, array $rows, int $limit): void
{
    foreach ($rows as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $list[] = $item;
        if (count($list) >= $limit) {
            return;
        }
    }
}

function best_selling_products(mysqli $conn, int $limit = 8): array
{
    $limit = max(1, min(24, $limit));
    $statuses = best_selling_status_sql();

    $salesSubquery = "SELECT oi.banh_id, SUM(oi.quantity) AS sold_qty
                      FROM order_items oi
                      JOIN orders o ON o.id = oi.order_id
                      WHERE LOWER(o.status) IN ({$statuses})
                      GROUP BY oi.banh_id";

    $manualRes = $conn->query(
        "SELECT b.*, COALESCE(s.sold_qty, 0) AS sold_qty, 'manual' AS best_source
         FROM banh b
         LEFT JOIN ({$salesSubquery}) s ON s.banh_id = b.id
         WHERE b.is_hidden = 0 AND b.is_best_manual = 1
         ORDER BY (b.best_rank = 0), b.best_rank ASC, b.id DESC
         LIMIT {$limit}"
    );
    $manualRows = $manualRes ? $manualRes->fetch_all(MYSQLI_ASSOC) : [];

    $topSellingRes = $conn->query(
        "SELECT b.*, SUM(oi.quantity) AS sold_qty, 'auto' AS best_source
         FROM banh b
         JOIN order_items oi ON oi.banh_id = b.id
         JOIN orders o ON o.id = oi.order_id
         WHERE b.is_hidden = 0 AND LOWER(o.status) IN ({$statuses})
         GROUP BY b.id
         ORDER BY sold_qty DESC, b.id DESC
         LIMIT {$limit}"
    );
    $topSellingRows = $topSellingRes ? $topSellingRes->fetch_all(MYSQLI_ASSOC) : [];

    $bestList = [];
    $seen = [];
    best_selling_append_unique($bestList, $seen, $manualRows, $limit);
    best_selling_append_unique($bestList, $seen, $topSellingRows, $limit);

    if (count($bestList) < $limit) {
        $fallbackLimit = $limit - count($bestList);
        $fallbackRes = $conn->query(
            "SELECT b.*, 0 AS sold_qty, 'fallback' AS best_source
             FROM banh b
             WHERE b.is_hidden = 0
             ORDER BY b.id DESC
             LIMIT {$fallbackLimit}"
        );
        $fallbackRows = $fallbackRes ? $fallbackRes->fetch_all(MYSQLI_ASSOC) : [];
        best_selling_append_unique($bestList, $seen, $fallbackRows, $limit);
    }

    return $bestList;
}
