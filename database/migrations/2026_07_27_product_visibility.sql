-- Add soft-hide support for products. Safe to run repeatedly on MySQL 8.
DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_product_visibility$$

CREATE PROCEDURE migrate_product_visibility()
BEGIN
    DECLARE item_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'banh'
      AND COLUMN_NAME = 'is_hidden';
    IF item_exists = 0 THEN
        ALTER TABLE banh
            ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    SELECT COUNT(*) INTO item_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'banh'
      AND INDEX_NAME = 'idx_banh_hidden_loai';
    IF item_exists = 0 THEN
        ALTER TABLE banh
            ADD INDEX idx_banh_hidden_loai (is_hidden, loai);
    END IF;
END$$

CALL migrate_product_visibility()$$
DROP PROCEDURE migrate_product_visibility$$
DELIMITER ;
