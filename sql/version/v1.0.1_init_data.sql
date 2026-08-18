-- =============================================================
-- Nice Fold 德州酒馆 初始化基础数据
-- 版本: v1.0.1
-- 说明: 可重复执行（幂等），不会覆盖已存在的数据
-- =============================================================

SET NAMES utf8mb4;

-- 桌号（设计稿 shoping-checkout-select-table.png）
INSERT INTO `nf_table` (`name`, `sort`, `status`)
SELECT '大桌', 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_table` WHERE `name` = '大桌');
INSERT INTO `nf_table` (`name`, `sort`, `status`)
SELECT '小桌', 2, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_table` WHERE `name` = '小桌');
INSERT INTO `nf_table` (`name`, `sort`, `status`)
SELECT '一楼', 3, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `nf_table` WHERE `name` = '一楼');

-- 系统配置
INSERT IGNORE INTO `nf_setting` (`group`, `key`, `value`, `remark`) VALUES
('base',  'shop_name',            'Nice Fold 德州酒馆', '门店名称'),
('base',  'shop_phone',           '',                   '门店电话'),
('base',  'shop_address',         '',                   '门店地址'),
('base',  'shop_notice',          '',                   '门店公告'),
('base',  'business_hours',       '',                   '营业时间'),
('point', 'point_to_gift_rate',   '300',                '记分牌兑换赠金比例，记分牌:赠金 = 300:1'),
('point', 'gift_default_days',    '0',                  '赠金默认有效天数，0表示永久'),
('order', 'auto_cancel_minutes',  '15',                 '未支付订单自动取消分钟数'),
('wine',  'default_expire_days',  '90',                 '存酒默认保存天数，0表示不过期'),
('wine',  'take_code_expire_min', '10',                 '取酒码有效分钟数');

-- 默认超级管理员：admin / admin123（上线后必须立即修改密码）
INSERT IGNORE INTO `nf_admin_user` (`username`, `password`, `real_name`, `role`, `status`) VALUES
('admin', '$2y$12$r9Od8r34e2XBwUbpUNlx7./F84a2XlrDP.v9tssJXoiWs6pROk8/G', '超级管理员', 1, 1);
