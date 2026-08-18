-- =============================================================
-- 新增接单打印机配置与打印日志
-- 版本: v1.0.4
-- 幂等，可重复执行；仅 CREATE TABLE IF NOT EXISTS，不含任何删除/重建操作
-- =============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `nf_printer` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)  NOT NULL DEFAULT ''  COMMENT '打印机名称/备注',
  `vendor`      TINYINT      NOT NULL DEFAULT 1   COMMENT '厂商 1飞鹅云打印 2芯烨云打印 3商米云打印',
  `sn`          VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '打印机终端编号(SN)',
  `account`     VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '开放平台账号(飞鹅User/芯烨User/商米AppId)',
  `secret_key`  VARCHAR(128) NOT NULL DEFAULT ''  COMMENT '密钥(飞鹅UKEY/芯烨UKEY/商米AppSecret)',
  `copies`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '打印联数',
  `voice_times` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '语音提醒次数，0为不提醒(仅飞鹅/芯烨支持)',
  `status`      TINYINT      NOT NULL DEFAULT 1   COMMENT '状态 1启用(参与自动出单) 0停用',
  `sort`        INT          NOT NULL DEFAULT 0   COMMENT '排序，越小越前',
  `remark`      VARCHAR(200) NOT NULL DEFAULT ''  COMMENT '备注',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='接单打印机配置';

CREATE TABLE IF NOT EXISTS `nf_print_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `printer_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '打印机ID',
  `order_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0  COMMENT '订单ID，0为测试打印',
  `order_no`    VARCHAR(40)  NOT NULL DEFAULT ''  COMMENT '订单号',
  `vendor`      TINYINT      NOT NULL DEFAULT 0   COMMENT '厂商，冗余便于统计',
  `content`     TEXT         NULL                 COMMENT '打印内容',
  `status`      TINYINT      NOT NULL DEFAULT 0   COMMENT '状态 0待打印 1成功 2失败',
  `third_no`    VARCHAR(64)  NOT NULL DEFAULT ''  COMMENT '第三方返回流水号',
  `fail_reason` VARCHAR(255) NOT NULL DEFAULT ''  COMMENT '失败原因',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_printer` (`printer_id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='打印日志';
