-- Thêm liên kết Auth0 vào bảng users (mirror danh tinh).
ALTER TABLE `users`
  ADD COLUMN `auth0_id` VARCHAR(64) NULL AFTER `id`,
  ADD UNIQUE KEY `uniq_users_auth0_id` (`auth0_id`);
