<?php
if (!function_exists('ensureAuth0Infrastructure')) {
    function ensureAuth0Infrastructure(mysqli $conn): void
    {
        $col = $conn->query("SHOW COLUMNS FROM `users` LIKE 'auth0_id'");
        if ($col && $col->num_rows > 0) {
            return;
        }
        // Cột chưa có: thêm cột + unique key.
        $conn->query(
            "ALTER TABLE `users` "
            . "ADD COLUMN `auth0_id` VARCHAR(64) NULL AFTER `id`, "
            . "ADD UNIQUE KEY `uniq_users_auth0_id` (`auth0_id`)"
        );
    }
}
