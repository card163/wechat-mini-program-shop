-- =============================================================
-- 商品支持"购买赠送饮品卡"：编辑商品时可配置赠送数量与有效期
-- 版本: v1.0.7
-- 幂等，可重复执行；不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

-- nf_goods 新增"购买赠送数量"与"赠送有效天数"
SET @col_gift_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'drink_card_gift_amount'
);
SET @ddl_gift_amount := IF(
  @col_gift_amount = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `drink_card_gift_amount` BIGINT NOT NULL DEFAULT 0 COMMENT '购买该商品(单件)赠送饮品卡数量，单位见配置point.drink_card_unit，0表示不赠送' AFTER `drink_card_amount`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_gift_amount;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_gift_expire := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'drink_card_gift_expire_days'
);
SET @ddl_gift_expire := IF(
  @col_gift_expire = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `drink_card_gift_expire_days` INT NOT NULL DEFAULT 0 COMMENT '购买赠送饮品卡有效天数，0表示永久有效' AFTER `drink_card_gift_amount`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_gift_expire;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- nf_order_item 新增下单时的赠送数量/有效天数快照（避免支付完成前商品配置变更影响已下单订单）
SET @col_item_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_order_item' AND COLUMN_NAME = 'drink_card_gift_amount'
);
SET @ddl_item_amount := IF(
  @col_item_amount = 0,
  "ALTER TABLE `nf_order_item` ADD COLUMN `drink_card_gift_amount` BIGINT NOT NULL DEFAULT 0 COMMENT '下单时商品赠送饮品卡数量快照(单件)' AFTER `subtotal`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_item_amount;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_item_expire := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_order_item' AND COLUMN_NAME = 'drink_card_gift_expire_days'
);
SET @ddl_item_expire := IF(
  @col_item_expire = 0,
  "ALTER TABLE `nf_order_item` ADD COLUMN `drink_card_gift_expire_days` INT NOT NULL DEFAULT 0 COMMENT '下单时赠送饮品卡有效天数快照' AFTER `drink_card_gift_amount`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl_item_expire;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
