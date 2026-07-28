CREATE TABLE IF NOT EXISTS user_notifications (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(255) NOT NULL,
  href VARCHAR(255) DEFAULT NULL,
  icon VARCHAR(60) NOT NULL DEFAULT 'fa-solid fa-bell',
  source_type VARCHAR(40) DEFAULT NULL,
  source_id INT(11) DEFAULT NULL,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_notifications_user_read_created (user_id, read_at, created_at),
  KEY idx_user_notifications_source (source_type, source_id),
  CONSTRAINT user_notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
