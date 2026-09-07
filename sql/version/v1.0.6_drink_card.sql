-- =============================================================
-- 新增「饮品卡」资产类型（独立于赠金，与赠金结构对称：批次+过期，仅管理后台手动发放/调整）
-- 版本: v1.0.6
-- 幂等，可重复执行；不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

-- 饮品卡展示名称与计量单位（管理后台"系统设置-记分牌与赠金"可修改）
INSERT IGNORE INTO `nf_setting` (`group`, `key`, `value`, `remark`) VALUES
('point', 'drink_card_display_name', '饮品卡', '饮品卡功能对外展示名称，仅影响展示文案'),
('point', 'drink_card_unit',         '元',   '饮品卡计量单位：元(按金额分记账，2位小数展示) 或 张(按整数张数记账)');

-- nf_member 新增饮品卡余额字段
SET @col_member := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_member' AND COLUMN_NAME = 'drink_card_balance'
);
SET @ddl_member := IF(
  @col_member = 0,
  "ALTER TABLE `nf_member` ADD COLUMN `drink_card_balance` BIGINT NOT NULL DEFAULT 0 COMMENT '有效饮品卡余额，由未过期批次汇总' AFTER `gift_balance`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_member;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 饮品卡批次表（结构与赠金批次对称，按 expired_at 由近及远消耗）
CREATE TABLE IF NOT EXISTS `nf_member_drink_card_batch` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `amount`        BIGINT       NOT NULL DEFAULT 0   COMMENT '本批次发放数量',
  `used_amount`   BIGINT       NOT NULL DEFAULT 0   COMMENT '已消耗数量',
  `remain_amount` BIGINT       NOT NULL DEFAULT 0   COMMENT '剩余可用数量',
  `source_type`   TINYINT      NOT NULL DEFAULT 1   COMMENT '来源 1管理员发放 2订单退款回滚',
  `source_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '来源单据ID',
  `effective_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '生效时间',
  `expired_at`    DATETIME     NULL     DEFAULT NULL COMMENT '过期时间，NULL 表示永久有效',
  `status`        TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1有效 2已用完 3已过期',
  `remark`        VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '备注',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_status_expire` (`member_id`, `status`, `expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员饮品卡批次';

-- nf_member_balance_log 新增饮品卡批次ID列（account_type=3 时有效，与既有 gift_batch_id 分列，不影响历史数据）
SET @col_log := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_member_balance_log' AND COLUMN_NAME = 'drink_card_batch_id'
);
SET @ddl_log := IF(
  @col_log = 0,
  "ALTER TABLE `nf_member_balance_log` ADD COLUMN `drink_card_batch_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '饮品卡批次ID，account_type=3 时有效' AFTER `gift_batch_id`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_log;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- nf_goods 新增"是否可用饮品卡支付"与"固定消耗量"
SET @col_payable := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'drink_card_payable'
);
SET @ddl_payable := IF(
  @col_payable = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `drink_card_payable` TINYINT NOT NULL DEFAULT 0 COMMENT '是否可用饮品卡支付 1是 0否' AFTER `gift_amount`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_payable;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'drink_card_amount'
);
SET @ddl_amount := IF(
  @col_amount = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `drink_card_amount` BIGINT NOT NULL DEFAULT 0 COMMENT '使用饮品卡支付时兑换该商品(单件)需消耗的数量，单位见配置point.drink_card_unit' AFTER `drink_card_payable`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_amount;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- nf_order 新增饮品卡支付金额字段
SET @col_order := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_order' AND COLUMN_NAME = 'pay_drink_card'
);
SET @ddl_order := IF(
  @col_order = 0,
  "ALTER TABLE `nf_order` ADD COLUMN `pay_drink_card` BIGINT NOT NULL DEFAULT 0 COMMENT '饮品卡支付金额' AFTER `pay_gift`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_order;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
