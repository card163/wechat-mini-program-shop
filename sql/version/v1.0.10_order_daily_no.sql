-- =============================================================
-- 订单新增「每日出单序号」：每天从1开始递增，打印小票/订单列表展示，方便叫号
-- 版本: v1.0.10
-- 幂等，可重复执行；仅新增表/列，不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `nf_order_daily_sequence` (
  `biz_date`   DATE            NOT NULL             COMMENT '业务日期(本地日期)，主键',
  `seq`        INT UNSIGNED    NOT NULL DEFAULT 0    COMMENT '当日已分配的最大出单序号',
  `created_at` DATETIME        NULL,
  `updated_at` DATETIME        NULL,
  PRIMARY KEY (`biz_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单每日出单序号计数器（无自增主键，配合LAST_INSERT_ID(expr)原子取号）';

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_order' AND COLUMN_NAME = 'daily_no'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `nf_order` ADD COLUMN `daily_no` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''每日出单序号(每天从1递增，用于叫号/小票)'' AFTER `order_no`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
