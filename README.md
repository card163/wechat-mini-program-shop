# 轻量级微信小程序商城 - 会员点单小程序

基于 **PHP (webman)** + **微信小程序** 的会员点单系统。核心场景：到店扫码点单 → 选桌号 → 微信支付 / 余额支付 → 累计积分与赠金 → 存酒取酒 → 积分兑换 → 排行榜。

## 技术栈

| 层 | 技术 | 目录 |
|---|---|---|
| 后端 | PHP 8.4 + webman | `server/` |
| 数据库 | MySQL 8.0 | `sql/` |
| 管理后台 | Vue 3 + Vite + TypeScript | `web-admin/` |
| 小程序 | 微信原生小程序 | `mini-program/` |
| 运维脚本 | Shell | `sh/` |

## 目录结构

```
docs/design/      设计稿截图（需求的唯一视觉依据）
docs/api/         接口文档
docs/Hi-Fi/       高保真原型
server/           webman 后端（PHP）
web-admin/        Vue3 管理后台
mini-program/     微信原生小程序
sql/version/      数据库变更迁移脚本
sh/               部署与运维脚本
```

## 核心功能

- 首页 / 点单 / 结算（选桌号、微信支付、余额支付）
- 充值（充值套餐、充值赠送）
- 个人中心（余额、积分、赠金、订单）
- 积分流水、积分兑换
- 赠金记录（按批次发放，有效期内仅限店内消费）
- 存酒取酒（小程序码登记与核销）
- 会员排行榜
- 支持飞鹅云打印、芯烨云打印、商米云打印三家厂商，覆盖配置绑定、自动出单、失败补打印全流程

## 快速开始

### 后端

```bash
cd server
composer install
php start.php start
```

### 管理后台

```bash
cd web-admin
npm install
npm run dev
```

### 小程序

使用微信开发者工具直接导入 `mini-program/` 目录。

详细开发约定见 [AGENTS.md](AGENTS.md)。
