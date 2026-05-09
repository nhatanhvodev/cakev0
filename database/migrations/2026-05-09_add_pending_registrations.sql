-- Operator note: verify there are no duplicate email values among live users before adding the unique index in production.

CREATE TABLE IF NOT EXISTS `pending_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pending_registrations_username` (`username`),
  UNIQUE KEY `uniq_pending_registrations_email` (`email`),
  UNIQUE KEY `uniq_pending_registrations_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Operator note: clean up any rows where `email IS NULL` before making `users.email` NOT NULL in production.
ALTER TABLE `users`
  MODIFY `email` varchar(255) NOT NULL,
  ADD UNIQUE KEY `uniq_users_email` (`email`);
