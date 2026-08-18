# AGENTS.md

本项目为 AI Agent 参与开发的项目。所有 Agent 在动手前必须先读本文件，并遵守以下约定。

## 一、项目简介

「Nice Fold 德州酒馆」会员点单小程序。核心场景：到店扫码点单 → 选桌号 → 微信支付 / 余额支付 → 累计积分与赠金 → 存酒取酒 → 积分兑换 → 排行榜。

## 二、技术栈（不得擅自更换）

| 层 | 技术 | 目录 |
|---|---|---|
| 后端 | PHP 8.4 + webman | `server/` |
| 数据库 | MySQL 8.0（utf8mb4 / utf8mb4_general_ci） | `sql/` |
| 管理后台 | Vue 3 + Vite + TypeScript | `web-admin/` |
| 小程序 | 微信原生小程序（非 uni-app、非 Taro） | `mini-program/` |
| 运维脚本 | Shell | `sh/` |

## 三、目录职责

```
docs/design/      设计稿截图（已完成，是需求的唯一视觉依据，不得修改）
docs/api/         接口文档（按模块拆分 Markdown）
docs/Hi-Fi/       高保真原型
server/           webman 后端
web-admin/        Vue3 管理后台
mini-program/     微信原生小程序
sql/version/      数据库变更迁移脚本，按版本号命名
sh/               部署与运维脚本
```

## 四、业务模块（对应设计稿）

| 模块 | 设计稿 | 说明 |
|---|---|---|
| 首页 | `index.png` | 门店信息、功能入口 |
| 点单 | `shoping-index.png` | 商品分类、商品列表、购物车 |
| 结算 | `shoping-checkout.png`、`shoping-checkout-select-table.png` | 选择桌号（大桌/小桌/一楼）、支付方式（微信支付 / 余额支付）、订单备注 |
| 充值 | `rechage.png` | 充值套餐，充值赠送 |
| 个人中心 | `me-index.png` | 余额、积分、赠金、订单、存酒入口 |
| 我的订单 | `me-order-list.png` | 订单列表与状态 |
| 积分流水 | `me-sorce-rows.png` | 积分变动记录 |
| 积分兑换 | `me-exchage-sorce.png` | 积分兑换商品 |
| 赠金记录 | `me-gift-sorce-rows.png` | 赠金按批次发放、带有效期，仅限店内消费 |
| 我的存酒 | `me-store-drink.png`、`me-store-drink-qrcode.png` | 存酒码（小程序码带 scene）由店员扫码登记；取酒码由店员核销 |
| 排行榜 | `ranking.png` | 会员榜单 |

### 资金与积分规则（来自设计稿文案，实现时必须遵守）
- 余额分为「本金」与「赠金」两个独立账户，赠金按批次记录、可过期。
- 赠金仅限兑换店内酒水小吃，不可转赠、不可交易、不可提现。
- 记分牌 : 赠金 = 300 : 1。
- 所有资金/积分变动必须落流水表，不允许只改余额字段。

## 五、编码约定

### 后端（webman）
- 严格模式：每个 PHP 文件首行 `declare(strict_types=1);`。
- 分层：`app/controller` 只做参数校验与响应组装，业务写在 `app/service`，数据访问写在 `app/model`（ORM 用 illuminate/database）。
- 统一响应体：`{ "code": 0, "msg": "ok", "data": {} }`，`code` 非 0 表示业务失败；HTTP 状态码保持 200。
- 金额一律用整数「分」存储与传输，禁止用 float 做金额计算。
- 参数校验用 `respect/validation` 或独立 Request 类，禁止在业务层裸取 `$request->post()`。
- 涉及余额、积分、库存的写操作必须开启事务并加行锁（`SELECT ... FOR UPDATE`）。
- 小程序端接口前缀 `/api/`，管理后台接口前缀 `/admin/`。

### 小程序（原生）
- 目录：`pages/`、`components/`、`utils/`、`api/`。
- 网络请求统一封装在 `utils/request.js`，自动带 token、统一错误提示，禁止页面里直接写 `wx.request`。
- 深色主题：背景近黑、主色暗红、强调色金黄（以设计稿为准，颜色统一定义在 `app.wxss` 变量中）。

### 管理后台（Vue3）
- `<script setup>` + TypeScript + Composition API，禁止 Options API。
- 状态管理 Pinia，请求封装在 `src/api/`，基于 axios 拦截器统一处理响应体与鉴权。

### 数据库
- 统一表前缀 `nf_`，表名小写下划线且用单数（如 `nf_order`、`nf_order_item`）；每张表必备 `id`、`created_at`、`updated_at`。
- 所有表结构变更写成 `sql/version/` 下的独立迁移脚本（如 `v1.0.2_add_gift_batch.sql`），只能新增文件，**禁止改动已发布的脚本**；脚本必须幂等（`CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`）。
- 禁止使用外键约束，关联关系在应用层保证。
- 金额字段一律 `BIGINT` 存「分」；状态字段一律 `TINYINT` 并在 COMMENT 中写明取值含义。
- 已有脚本：`v1.0.0_init.sql`（19 张表）、`v1.0.1_init_data.sql`（桌号、系统配置、默认管理员）。

## 六、红线（绝对禁止）

1. 禁止 `DROP DATABASE` / `CREATE DATABASE` / `DROP TABLE` / `TRUNCATE`，任何部署脚本都不得包含数据库删除或重建操作。
2. 禁止把导入备份 SQL 当作部署步骤；备份仅用于灾难恢复。
3. 部署 = 替换代码 + 安装依赖 + 重启进程，**不动数据库**；结构变更走 `sql/version/` 迁移脚本单独执行。
4. 禁止修改 `docs/design/` 下的设计稿。
5. 禁止把 AppID、AppSecret、商户密钥、数据库密码写进代码或提交到仓库，一律走 `.env`，并确保 `.env` 已在 `.gitignore` 中。

## 七、开发流程

1. 先读设计稿 → 写/更新 `docs/api/` 接口文档 → 写迁移脚本 → 实现后端 → 实现前端。
2. 接口先定契约（路径、入参、出参示例）再写代码，前后端以 `docs/api/` 为准。
3. 改动完成后自检：后端 `composer check`（如已配置）或 `php -l`，前端 `npm run build` 可通过。
