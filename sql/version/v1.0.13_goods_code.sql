-- =============================================================
-- 商品新增"外部编码"字段（对接第三方POS/供应链，选填，应用层保证同店铺内唯一）
-- 版本: v1.0.13
-- 幂等，可重复执行；不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

SET @col_code := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'code'
);
SET @ddl_code := IF(
  @col_code = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '外部编码，选填，应用层校验同店铺内唯一' AFTER `unit`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_code;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_code := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND INDEX_NAME = 'idx_code'
);
SET @ddl_idx_code := IF(
  @idx_code = 0,
  "ALTER TABLE `nf_goods` ADD INDEX `idx_code` (`code`)",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_idx_code;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
