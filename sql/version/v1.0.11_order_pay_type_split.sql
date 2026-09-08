-- 支付方式由「微信支付/余额支付(组合)」拆分为四种互相独立的支付方式，仅更新字段注释，不改变列类型与数据
ALTER TABLE `nf_order`
  MODIFY COLUMN `pay_type` TINYINT NOT NULL DEFAULT 1 COMMENT '支付方式 1微信支付 2余额(本金)支付 3酒水卡支付 4饮品卡支付';
