<?php

require_once __DIR__ . '/config.php';

if (!function_exists('ensureProductVisibilityInfrastructure')) {
    function ensureProductVisibilityInfrastructure(mysqli $conn): void
    {
        $tableResult = $conn->query("SHOW TABLES LIKE 'banh'");
        if (!$tableResult || $tableResult->num_rows === 0) {
            if ($tableResult instanceof mysqli_result) {
                $tableResult->free();
            }
            return;
        }
        $tableResult->free();

        $columnResult = $conn->query("SHOW COLUMNS FROM banh LIKE 'is_hidden'");
        if ($columnResult && $columnResult->num_rows === 0) {
            $conn->query("ALTER TABLE banh ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
        }
        if ($columnResult instanceof mysqli_result) {
            $columnResult->free();
        }

        $indexResult = $conn->query("SHOW INDEX FROM banh WHERE Key_name = 'idx_banh_hidden_loai'");
        if ($indexResult && $indexResult->num_rows === 0) {
            $conn->query("ALTER TABLE banh ADD INDEX idx_banh_hidden_loai (is_hidden, loai)");
        }
        if ($indexResult instanceof mysqli_result) {
            $indexResult->free();
        }
    }
}
