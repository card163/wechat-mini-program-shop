-- =============================================================
-- 飞鹅云打印机新增区域字段（国内/东南亚，接口域名不同）
-- 版本: v1.0.9
-- 幂等，可重复执行；仅新增列，不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_printer' AND COLUMN_NAME = 'area'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `nf_printer` ADD COLUMN `area` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''区域 1国内 2东南亚(仅飞鹅云打印生效)'' AFTER `vendor`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
