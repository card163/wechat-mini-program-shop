-- =============================================================
-- 赠金展示名称/计量单位可配置 + 商品固定赠金消耗量
-- 版本: v1.0.5
-- 幂等，可重复执行；不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

-- 赠金展示名称与计量单位（管理后台"系统设置-记分牌与赠金"可修改）
-- 仅影响展示文案与录入换算，数据库字段名、接口字段名、代码变量名一律保留"赠金/gift"不变
INSERT IGNORE INTO `nf_setting` (`group`, `key`, `value`, `remark`) VALUES
('point', 'gift_display_name', '赠金', '赠金功能对外展示名称，如改为"酒水卡"，仅影响展示文案'),
('point', 'gift_unit',         '元',   '赠金计量单位：元(按金额分记账，2位小数展示) 或 张(按整数张数记账)');

-- nf_goods 新增"使用赠金支付需消耗的赠金数量"字段
-- 用信息库检查代替 ADD COLUMN IF NOT EXISTS，兼容更早的 MySQL 8.0 小版本
SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nf_goods' AND COLUMN_NAME = 'gift_amount'
);
SET @ddl := IF(
  @column_exists = 0,
  "ALTER TABLE `nf_goods` ADD COLUMN `gift_amount` BIGINT NOT NULL DEFAULT 0 COMMENT '使用赠金支付时兑换该商品(单件)需消耗的赠金数量，单位见配置point.gift_unit' AFTER `gift_payable`",
  "SELECT 1"
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 历史数据兼容：已允许赠金支付但尚未设置消耗量的商品，默认按现金售价 1:1 换算（保持旧版本"赠金按金额抵扣"行为不变）
UPDATE `nf_goods` SET `gift_amount` = `price` WHERE `gift_payable` = 1 AND `gift_amount` = 0 AND `price` > 0;
