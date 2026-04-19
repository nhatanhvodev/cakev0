-- Add Clerk user mapping for legacy local users table.
-- Safe to run multiple times.

SET @schema_name = DATABASE();

SET @has_clerk_user_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'clerk_user_id'
);

SET @sql_add_column := IF(
  @has_clerk_user_id = 0,
  'ALTER TABLE users ADD COLUMN clerk_user_id VARCHAR(255) NULL AFTER id',
  'SELECT 1'
);

PREPARE stmt_add_column FROM @sql_add_column;
EXECUTE stmt_add_column;
DEALLOCATE PREPARE stmt_add_column;

SET @has_clerk_user_id_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'uniq_users_clerk_user_id'
);

SET @sql_add_index := IF(
  @has_clerk_user_id_index = 0,
  'ALTER TABLE users ADD UNIQUE KEY uniq_users_clerk_user_id (clerk_user_id)',
  'SELECT 1'
);

PREPARE stmt_add_index FROM @sql_add_index;
EXECUTE stmt_add_index;
DEALLOCATE PREPARE stmt_add_index;
