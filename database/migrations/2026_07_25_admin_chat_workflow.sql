-- Additive migration for the admin support workflow.
-- Safe to run repeatedly on MySQL 8.
DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_admin_chat_workflow$$

CREATE PROCEDURE migrate_admin_chat_workflow()
BEGIN
    DECLARE item_exists INT DEFAULT 0;

    ALTER TABLE chat_sessions
        MODIFY status ENUM(
            'active',
            'open',
            'handoff',
            'in_progress',
            'closed'
        ) NOT NULL DEFAULT 'active';

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND COLUMN_NAME = 'assigned_admin_id';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD COLUMN assigned_admin_id INT NULL AFTER metadata;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND COLUMN_NAME = 'assigned_at';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD COLUMN assigned_at TIMESTAMP NULL AFTER assigned_admin_id;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND COLUMN_NAME = 'closed_by_admin_id';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD COLUMN closed_by_admin_id INT NULL AFTER closed_at;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND COLUMN_NAME = 'reopened_at';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD COLUMN reopened_at TIMESTAMP NULL AFTER closed_by_admin_id;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND INDEX_NAME = 'idx_chat_assigned_admin';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD INDEX idx_chat_assigned_admin (
                assigned_admin_id,
                status
            );
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND CONSTRAINT_NAME = 'fk_chat_assigned_admin';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD CONSTRAINT fk_chat_assigned_admin
            FOREIGN KEY (assigned_admin_id)
            REFERENCES admins(id)
            ON DELETE SET NULL;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_sessions'
      AND CONSTRAINT_NAME = 'fk_chat_closed_by_admin';
    IF item_exists = 0 THEN
        ALTER TABLE chat_sessions
            ADD CONSTRAINT fk_chat_closed_by_admin
            FOREIGN KEY (closed_by_admin_id)
            REFERENCES admins(id)
            ON DELETE SET NULL;
    END IF;

    CREATE TABLE IF NOT EXISTS chat_session_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        admin_id INT NULL,
        event_type VARCHAR(40) NOT NULL,
        from_status VARCHAR(24) NULL,
        to_status VARCHAR(24) NULL,
        metadata JSON NULL,
        created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
        INDEX idx_chat_event_session (session_id, id),
        INDEX idx_chat_event_admin (admin_id, created_at),
        CONSTRAINT fk_chat_event_session
            FOREIGN KEY (session_id)
            REFERENCES chat_sessions(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_chat_event_admin
            FOREIGN KEY (admin_id)
            REFERENCES admins(id)
            ON DELETE SET NULL
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci;

    UPDATE chat_sessions
    SET status = 'open'
    WHERE status = 'handoff';
END$$

CALL migrate_admin_chat_workflow()$$
DROP PROCEDURE migrate_admin_chat_workflow$$

DELIMITER ;
