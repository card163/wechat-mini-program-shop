-- =============================================================
-- 桌号先划区再加桌号：新增分区表 nf_table_zone，nf_table 关联所属分区
-- 版本: v1.0.8
-- 幂等，可重复执行；不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

-- 分区表（如：一号台 / 二号台）
CREATE TABLE IF NOT EXISTS `nf_table_zone` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(32) NOT NULL DEFAULT ''  COMMENT '分区名称，如 一号台/二号台',
  `sort`       INT         NOT NULL DEFAULT 0   COMMENT '排序',
  `status`     TINYINT     NOT NULL DEFAULT 1   COMMENT '状态 1启用 0停用',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='桌号分区';

-- nf_table 新增所属分区ID
SET @col_zone_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_table' AND COLUMN_NAME = 'zone_id'
);
SET @ddl_zone_id := IF(
  @col_zone_id = 0,
  "ALTER TABLE `nf_table` ADD COLUMN `zone_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属分区ID，对应nf_table_zone.id，0表示未分区(历史数据)' AFTER `id`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_zone_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 历史数据兼容：把未分区的旧桌号归到"默认分区"，避免升级后在小程序端消失
INSERT INTO `nf_table_zone` (`name`, `sort`, `status`)
SELECT '默认分区', 0, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_table_zone` WHERE `name` = '默认分区');

UPDATE `nf_table` t
JOIN `nf_table_zone` z ON z.`name` = '默认分区'
SET t.`zone_id` = z.`id`
WHERE t.`zone_id` = 0;
