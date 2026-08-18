-- =============================================================
-- Nice Fold 德州酒馆 会员点单小程序 初始化表结构
-- 版本: v1.0.0
-- 说明: 所有金额字段单位为「分」(BIGINT)，禁止使用浮点数
--       全表无外键约束，关联关系由应用层保证
--       本脚本只做 CREATE TABLE IF NOT EXISTS，不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- 1. 会员
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_member` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `openid`         VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '微信小程序 openid',
  `unionid`        VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '微信 unionid',
  `nickname`       VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '昵称',
  `avatar`         VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '头像地址',
  `phone`          VARCHAR(20)  NOT NULL DEFAULT ''  COMMENT '手机号',
  `balance`        BIGINT       NOT NULL DEFAULT 0   COMMENT '本金余额(分)',
  `gift_balance`   BIGINT       NOT NULL DEFAULT 0   COMMENT '有效赠金余额(分)，由未过期批次汇总',
  `point`          BIGINT       NOT NULL DEFAULT 0   COMMENT '当前记分牌(积分)余额',
  `total_point`    BIGINT       NOT NULL DEFAULT 0   COMMENT '累计获得记分牌，用于排行榜',
  `total_recharge` BIGINT       NOT NULL DEFAULT 0   COMMENT '累计充值本金(分)',
  `total_consume`  BIGINT       NOT NULL DEFAULT 0   COMMENT '累计消费(分)',
  `status`         TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1正常 0禁用',
  `remark`         VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '管理员备注',
  `last_login_at`  DATETIME     NULL     DEFAULT NULL COMMENT '最后登录时间',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`),
  KEY `idx_phone` (`phone`),
  KEY `idx_total_point` (`total_point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员';

-- -------------------------------------------------------------
-- 2. 赠金批次（赠金按批次发放、独立有效期、先到期先消耗）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_member_gift_batch` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `amount`       BIGINT       NOT NULL DEFAULT 0   COMMENT '本批次发放赠金(分)',
  `used_amount`  BIGINT       NOT NULL DEFAULT 0   COMMENT '已消耗(分)',
  `remain_amount` BIGINT      NOT NULL DEFAULT 0   COMMENT '剩余可用(分)',
  `source_type`  TINYINT      NOT NULL DEFAULT 1   COMMENT '来源 1充值赠送 2记分牌兑换 3管理员发放 4订单退款回滚',
  `source_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '来源单据ID',
  `effective_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '生效时间',
  `expired_at`   DATETIME     NULL     DEFAULT NULL COMMENT '过期时间，NULL 表示永久有效',
  `status`       TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1有效 2已用完 3已过期',
  `remark`       VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '备注',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_status_expire` (`member_id`, `status`, `expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员赠金批次';

-- -------------------------------------------------------------
-- 3. 余额流水（本金 / 赠金 共用，所有资金变动必须落此表）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_member_balance_log` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `account_type`   TINYINT      NOT NULL DEFAULT 1   COMMENT '账户 1本金 2赠金',
  `gift_batch_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '赠金批次ID，account_type=2 时有效',
  `amount`         BIGINT       NOT NULL DEFAULT 0   COMMENT '变动金额(分)，正数增加、负数减少',
  `before_balance` BIGINT       NOT NULL DEFAULT 0   COMMENT '变动前余额(分)',
  `after_balance`  BIGINT       NOT NULL DEFAULT 0   COMMENT '变动后余额(分)',
  `biz_type`       TINYINT      NOT NULL DEFAULT 0   COMMENT '业务类型 1充值 2充值赠送 3消费 4退款 5记分牌兑换 6赠金过期 7管理员调整',
  `biz_id`         BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '关联业务单据ID',
  `biz_no`         VARCHAR(40)  NOT NULL DEFAULT ''  COMMENT '关联业务单号',
  `remark`         VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '备注',
  `operator_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '操作管理员ID，0为系统',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_created` (`member_id`, `created_at`),
  KEY `idx_biz` (`biz_type`, `biz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员余额流水';

-- -------------------------------------------------------------
-- 4. 记分牌(积分)流水
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_member_point_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `point`        BIGINT       NOT NULL DEFAULT 0   COMMENT '变动记分牌，正增负减',
  `before_point` BIGINT       NOT NULL DEFAULT 0   COMMENT '变动前记分牌',
  `after_point`  BIGINT       NOT NULL DEFAULT 0   COMMENT '变动后记分牌',
  `biz_type`     TINYINT      NOT NULL DEFAULT 0   COMMENT '业务类型 1店内存记分牌 2消费获得 3兑换赠金 4兑换商品 5管理员调整 6订单退款回滚',
  `biz_id`       BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '关联业务单据ID',
  `remark`       VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '备注',
  `operator_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '操作管理员ID，0为系统',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_created` (`member_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员记分牌流水';

-- -------------------------------------------------------------
-- 5. 商品分类
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_goods_category` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '分类名称',
  `icon`       VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '分类图标',
  `sort`       INT          NOT NULL DEFAULT 0   COMMENT '排序，值越小越靠前',
  `status`     TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1上架 0下架',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品分类';

-- -------------------------------------------------------------
-- 6. 商品
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_goods` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '分类ID',
  `name`         VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '商品名称',
  `subtitle`     VARCHAR(128) NOT NULL DEFAULT ''  COMMENT '副标题/规格描述',
  `cover`        VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '封面图',
  `images`       JSON         NULL     DEFAULT NULL COMMENT '轮播图数组',
  `price`        BIGINT       NOT NULL DEFAULT 0   COMMENT '售价(分)',
  `origin_price` BIGINT       NOT NULL DEFAULT 0   COMMENT '划线价(分)',
  `unit`         VARCHAR(16)  NOT NULL DEFAULT '份' COMMENT '单位',
  `stock`        INT          NOT NULL DEFAULT 0   COMMENT '库存，-1表示不限库存',
  `sales`        INT          NOT NULL DEFAULT 0   COMMENT '累计销量',
  `gift_payable` TINYINT      NOT NULL DEFAULT 1   COMMENT '是否可用赠金支付 1是 0否',
  `sort`         INT          NOT NULL DEFAULT 0   COMMENT '排序',
  `status`       TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1上架 0下架',
  `description`  TEXT         NULL     DEFAULT NULL COMMENT '商品详情',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_status_sort` (`category_id`, `status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品';

-- -------------------------------------------------------------
-- 7. 桌号（设计稿：大桌 / 小桌 / 一楼）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_table` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(32) NOT NULL DEFAULT ''  COMMENT '桌号名称，如 大桌/小桌/一楼',
  `sort`       INT         NOT NULL DEFAULT 0   COMMENT '排序',
  `status`     TINYINT     NOT NULL DEFAULT 1   COMMENT '状态 1启用 0停用',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='桌号';

-- -------------------------------------------------------------
-- 8. 点单订单
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_order` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`       VARCHAR(40)  NOT NULL DEFAULT ''  COMMENT '订单号',
  `member_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `table_id`       BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '桌号ID',
  `table_name`     VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '下单时的桌号名称快照',
  `total_amount`   BIGINT       NOT NULL DEFAULT 0   COMMENT '商品总额(分)',
  `pay_amount`     BIGINT       NOT NULL DEFAULT 0   COMMENT '应付金额(分)',
  `pay_type`       TINYINT      NOT NULL DEFAULT 1   COMMENT '支付方式 1微信支付 2余额支付',
  `pay_balance`    BIGINT       NOT NULL DEFAULT 0   COMMENT '本金支付金额(分)',
  `pay_gift`       BIGINT       NOT NULL DEFAULT 0   COMMENT '赠金支付金额(分)',
  `pay_wechat`     BIGINT       NOT NULL DEFAULT 0   COMMENT '微信支付金额(分)',
  `transaction_id` VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '微信支付交易号',
  `pay_status`     TINYINT      NOT NULL DEFAULT 0   COMMENT '支付状态 0待支付 1已支付 2已退款',
  `order_status`   TINYINT      NOT NULL DEFAULT 0   COMMENT '订单状态 0待支付 1已支付待出品 2已完成 3已取消',
  `gain_point`     BIGINT       NOT NULL DEFAULT 0   COMMENT '本单获得记分牌',
  `remark`         VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '订单备注',
  `paid_at`        DATETIME     NULL     DEFAULT NULL COMMENT '支付时间',
  `finished_at`    DATETIME     NULL     DEFAULT NULL COMMENT '完成时间',
  `cancelled_at`   DATETIME     NULL     DEFAULT NULL COMMENT '取消时间',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_member_created` (`member_id`, `created_at`),
  KEY `idx_status_created` (`order_status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='点单订单';

-- -------------------------------------------------------------
-- 9. 订单明细
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_order_item` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '订单ID',
  `goods_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '商品ID',
  `goods_name`  VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '商品名称快照',
  `goods_cover` VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '商品封面快照',
  `price`       BIGINT       NOT NULL DEFAULT 0   COMMENT '成交单价(分)',
  `quantity`    INT          NOT NULL DEFAULT 1   COMMENT '数量',
  `subtotal`    BIGINT       NOT NULL DEFAULT 0   COMMENT '小计(分)',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单明细';

-- -------------------------------------------------------------
-- 10. 充值套餐
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_recharge_package` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(64) NOT NULL DEFAULT ''  COMMENT '套餐标题',
  `amount`           BIGINT      NOT NULL DEFAULT 0   COMMENT '充值金额(分)，计入本金',
  `gift_amount`      BIGINT      NOT NULL DEFAULT 0   COMMENT '赠送赠金(分)',
  `gift_point`       BIGINT      NOT NULL DEFAULT 0   COMMENT '赠送记分牌',
  `gift_expire_days` INT         NOT NULL DEFAULT 0   COMMENT '赠金有效天数，0表示永久',
  `sort`             INT         NOT NULL DEFAULT 0   COMMENT '排序',
  `status`           TINYINT     NOT NULL DEFAULT 1   COMMENT '状态 1启用 0停用',
  `created_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值套餐';

-- -------------------------------------------------------------
-- 11. 充值订单
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_recharge_order` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`       VARCHAR(40) NOT NULL DEFAULT ''  COMMENT '充值单号',
  `member_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `package_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '套餐ID，0为自定义金额',
  `amount`         BIGINT      NOT NULL DEFAULT 0   COMMENT '实付金额(分)',
  `gift_amount`    BIGINT      NOT NULL DEFAULT 0   COMMENT '赠送赠金(分)',
  `gift_point`     BIGINT      NOT NULL DEFAULT 0   COMMENT '赠送记分牌',
  `transaction_id` VARCHAR(64) NOT NULL DEFAULT ''  COMMENT '微信支付交易号',
  `pay_status`     TINYINT     NOT NULL DEFAULT 0   COMMENT '支付状态 0待支付 1已支付 2已关闭',
  `paid_at`        DATETIME    NULL     DEFAULT NULL COMMENT '支付时间',
  `created_at`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_member_created` (`member_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值订单';

-- -------------------------------------------------------------
-- 12. 记分牌兑换商品
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_exchange_goods` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`        TINYINT      NOT NULL DEFAULT 1   COMMENT '兑换类型 1实物/酒水 2赠金',
  `name`        VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '名称',
  `cover`       VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '封面图',
  `point`       BIGINT       NOT NULL DEFAULT 0   COMMENT '所需记分牌',
  `gift_amount` BIGINT       NOT NULL DEFAULT 0   COMMENT 'type=2 时兑换所得赠金(分)',
  `gift_expire_days` INT     NOT NULL DEFAULT 0   COMMENT 'type=2 时赠金有效天数，0表示永久',
  `stock`       INT          NOT NULL DEFAULT -1  COMMENT '库存，-1表示不限',
  `exchanged`   INT          NOT NULL DEFAULT 0   COMMENT '已兑换数量',
  `description` VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '兑换说明',
  `sort`        INT          NOT NULL DEFAULT 0   COMMENT '排序',
  `status`      TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1上架 0下架',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记分牌兑换商品';

-- -------------------------------------------------------------
-- 13. 兑换记录（实物兑换需店员核销）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_exchange_record` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_no`   VARCHAR(40)  NOT NULL DEFAULT ''  COMMENT '兑换单号，同时作为核销码内容',
  `member_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `goods_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '兑换商品ID',
  `goods_name`  VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '商品名称快照',
  `type`        TINYINT      NOT NULL DEFAULT 1   COMMENT '兑换类型 1实物/酒水 2赠金',
  `point`       BIGINT       NOT NULL DEFAULT 0   COMMENT '消耗记分牌',
  `gift_amount` BIGINT       NOT NULL DEFAULT 0   COMMENT '兑换所得赠金(分)',
  `status`      TINYINT      NOT NULL DEFAULT 0   COMMENT '状态 0待核销 1已核销 2已取消',
  `verify_admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '核销店员ID',
  `verified_at` DATETIME     NULL     DEFAULT NULL COMMENT '核销时间',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_record_no` (`record_no`),
  KEY `idx_member_created` (`member_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记分牌兑换记录';

-- -------------------------------------------------------------
-- 14. 存酒（店员扫会员存酒码后登记）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_wine_storage` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `wine_name`     VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '酒名',
  `spec`          VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '规格',
  `unit`          VARCHAR(16)  NOT NULL DEFAULT '瓶' COMMENT '单位',
  `total_qty`     INT          NOT NULL DEFAULT 0   COMMENT '存入数量',
  `remain_qty`    INT          NOT NULL DEFAULT 0   COMMENT '剩余数量',
  `images`        JSON         NULL     DEFAULT NULL COMMENT '存酒照片',
  `status`        TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1存放中 2已取完 3已过期',
  `stored_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '存入时间',
  `expired_at`    DATETIME     NULL     DEFAULT NULL COMMENT '到期时间，NULL表示不过期',
  `store_admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登记店员ID',
  `remark`        VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '备注',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_status` (`member_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员存酒';

-- -------------------------------------------------------------
-- 15. 取酒记录（取酒码由店员核销）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_wine_take_record` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `take_no`    VARCHAR(40) NOT NULL DEFAULT ''  COMMENT '取酒码内容',
  `storage_id` BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '存酒ID',
  `member_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '会员ID',
  `quantity`   INT         NOT NULL DEFAULT 1   COMMENT '取出数量',
  `status`     TINYINT     NOT NULL DEFAULT 0   COMMENT '状态 0待核销 1已核销 2已失效',
  `verify_admin_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '核销店员ID',
  `verified_at` DATETIME   NULL     DEFAULT NULL COMMENT '核销时间',
  `code_expired_at` DATETIME NULL   DEFAULT NULL COMMENT '取酒码失效时间',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_take_no` (`take_no`),
  KEY `idx_member_created` (`member_id`, `created_at`),
  KEY `idx_storage` (`storage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='取酒记录';

-- -------------------------------------------------------------
-- 16. 后台管理员（含店员）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_admin_user` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '登录账号',
  `password`      VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '密码，password_hash 存储',
  `real_name`     VARCHAR(32)  NOT NULL DEFAULT ''  COMMENT '姓名',
  `avatar`        VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '头像',
  `phone`         VARCHAR(20)  NOT NULL DEFAULT ''  COMMENT '手机号',
  `role`          TINYINT      NOT NULL DEFAULT 2   COMMENT '角色 1超级管理员 2店员',
  `status`        TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1正常 0禁用',
  `last_login_at` DATETIME     NULL     DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '最后登录IP',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台管理员';

-- -------------------------------------------------------------
-- 17. 首页轮播图
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_banner` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '标题',
  `image`      VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '图片地址',
  `link`       VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '跳转小程序页面路径',
  `sort`       INT          NOT NULL DEFAULT 0   COMMENT '排序',
  `status`     TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1显示 0隐藏',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='首页轮播图';

-- -------------------------------------------------------------
-- 18. 系统配置（门店信息、积分规则等 KV 配置）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_setting` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group`      VARCHAR(32)  NOT NULL DEFAULT 'base' COMMENT '配置分组',
  `key`        VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '配置键',
  `value`      TEXT         NULL     DEFAULT NULL COMMENT '配置值',
  `remark`     VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '说明',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统配置';

-- -------------------------------------------------------------
-- 19. 微信支付回调日志（用于对账排查，回调必须幂等）
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nf_pay_notify_log` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `biz_type`       TINYINT     NOT NULL DEFAULT 1   COMMENT '业务类型 1点单订单 2充值订单',
  `order_no`       VARCHAR(40) NOT NULL DEFAULT ''  COMMENT '商户订单号',
  `transaction_id` VARCHAR(64) NOT NULL DEFAULT ''  COMMENT '微信交易号',
  `amount`         BIGINT      NOT NULL DEFAULT 0   COMMENT '回调金额(分)',
  `trade_state`    VARCHAR(32) NOT NULL DEFAULT ''  COMMENT '交易状态',
  `payload`        JSON        NULL     DEFAULT NULL COMMENT '完整回调报文',
  `handled`        TINYINT     NOT NULL DEFAULT 0   COMMENT '是否已处理 1是 0否',
  `created_at`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='微信支付回调日志';
