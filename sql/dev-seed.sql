-- =============================================================
-- 本地开发用演示数据（非迁移脚本，禁止在生产环境执行）
-- 可重复执行
-- 用法: mysql -uroot -p 'nf-shop' < sql/dev-seed.sql
-- =============================================================

SET NAMES utf8mb4;

-- 商品分类
INSERT INTO `nf_goods_category` (`name`, `sort`, `status`)
SELECT '威士忌', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods_category` WHERE `name` = '威士忌');
INSERT INTO `nf_goods_category` (`name`, `sort`, `status`)
SELECT '精酿啤酒', 2, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods_category` WHERE `name` = '精酿啤酒');
INSERT INTO `nf_goods_category` (`name`, `sort`, `status`)
SELECT '小吃', 3, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods_category` WHERE `name` = '小吃');

-- 商品
INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '威士忌'), '山崎12年', '单杯 30ml', 12800, 15800, '杯', -1, 1, 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = '山崎12年');

INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '威士忌'), '麦卡伦12年', '整瓶 700ml', 98000, 108000, '瓶', 10, 1, 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = '麦卡伦12年');

INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '精酿啤酒'), '德式小麦', '500ml', 3800, 0, '扎', 50, 1, 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = '德式小麦');

INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '精酿啤酒'), 'IPA 印度淡色艾尔', '500ml', 4200, 0, '扎', 50, 1, 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = 'IPA 印度淡色艾尔');

INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '小吃'), '香辣鸡翅', '6只', 3600, 0, '份', 30, 1, 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = '香辣鸡翅');

-- 不可用赠金支付的商品，用于校验赠金使用范围
INSERT INTO `nf_goods` (`category_id`, `name`, `subtitle`, `price`, `origin_price`, `unit`, `stock`, `gift_payable`, `sort`, `status`)
SELECT (SELECT `id` FROM `nf_goods_category` WHERE `name` = '小吃'), '果盘拼盘', '限本金支付', 8800, 0, '份', 20, 0, 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_goods` WHERE `name` = '果盘拼盘');

-- 充值套餐
INSERT INTO `nf_recharge_package` (`title`, `amount`, `gift_amount`, `gift_point`, `gift_expire_days`, `sort`, `status`)
SELECT '充500送50', 50000, 5000, 0, 90, 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_recharge_package` WHERE `title` = '充500送50');
INSERT INTO `nf_recharge_package` (`title`, `amount`, `gift_amount`, `gift_point`, `gift_expire_days`, `sort`, `status`)
SELECT '充1000送200', 100000, 20000, 0, 90, 2, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_recharge_package` WHERE `title` = '充1000送200');
INSERT INTO `nf_recharge_package` (`title`, `amount`, `gift_amount`, `gift_point`, `gift_expire_days`, `sort`, `status`)
SELECT '充3000送800', 300000, 80000, 0, 0, 3, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_recharge_package` WHERE `title` = '充3000送800');

-- 记分牌兑换
INSERT INTO `nf_exchange_goods` (`type`, `name`, `point`, `gift_amount`, `gift_expire_days`, `stock`, `description`, `sort`, `status`)
SELECT 2, '1元赠金', 300, 100, 90, -1, '300记分牌兑换1元赠金', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_exchange_goods` WHERE `name` = '1元赠金');
INSERT INTO `nf_exchange_goods` (`type`, `name`, `point`, `gift_amount`, `gift_expire_days`, `stock`, `description`, `sort`, `status`)
SELECT 1, '精酿啤酒一扎', 2000, 0, 0, 50, '凭兑换码到吧台核销', 2, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_exchange_goods` WHERE `name` = '精酿啤酒一扎');

-- 轮播图
INSERT INTO `nf_banner` (`title`, `image`, `link`, `sort`, `status`)
SELECT '新客首充有礼', 'https://placehold.co/750x360/1a1a1a/d4af37?text=66', '/pages/recharge/index', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_banner` WHERE `title` = '新客首充有礼');

-- 测试会员（openid 为占位值，仅本地联调使用）
INSERT INTO `nf_member` (`openid`, `nickname`, `phone`, `balance`, `gift_balance`, `point`, `total_point`, `status`)
SELECT 'dev_openid_tester', '测试牌友', '13800138000', 0, 0, 0, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_member` WHERE `openid` = 'dev_openid_tester');
