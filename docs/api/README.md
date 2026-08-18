# 接口文档 · 通用约定

> 本目录是前后端唯一契约来源。任何接口改动必须先改本目录文档，再改代码。

## 1. 基础信息

| 项 | 值 |
|---|---|
| 本地地址 | `http://127.0.0.1:8787` |
| 小程序端前缀 | `/api` |
| 管理后台前缀 | `/admin` |
| 健康检查 | `GET /health`（无鉴权） |
| 字符集 | UTF-8 |
| 请求体 | `application/x-www-form-urlencoded` 或 `application/json` |

## 2. 统一响应体

HTTP 状态码恒为 `200`（网络层错误除外），业务结果由 `code` 判定。

```json
{
  "code": 0,
  "msg": "ok",
  "data": {}
}
```

| code | 含义 | 前端处理 |
|---|---|---|
| 0 | 成功 | 正常取 `data` |
| 1 | 业务失败 | Toast 提示 `msg` |
| 401 | 未登录 / 登录失效 | 清除本地 token，重新登录 |
| 403 | 无权限 / 账号被禁用 | 提示并退出 |
| 404 | 资源或接口不存在 | 提示 |
| 500 | 服务端异常 | 提示"服务器繁忙" |

`data` 在无数据时返回 `{}`，不返回 `null`。

## 3. 鉴权

- 会员端与管理端使用**两套独立的 JWT 密钥**，token 不可互用。
- 请求头：`Authorization: Bearer <token>`。
- 会员 token 有效期 7 天，管理端 12 小时。
- token 失效统一返回 `code=401`。

## 4. 分页

请求参数：

| 参数 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `page` | int | 1 | 页码，从 1 开始 |
| `page_size` | int | 20 | 每页条数，最大 100 |

响应结构：

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [],
    "total": 128,
    "page": 1,
    "page_size": 20
  }
}
```

## 5. 金额与时间

- **所有金额字段单位为「分」，类型为整数**。前端展示时自行除以 100。
  例：`"pay_amount": 12800` 表示 128.00 元。
- 时间统一为 `YYYY-MM-DD HH:mm:ss` 字符串（东八区），时间戳字段以 `_at` 结尾且为字符串；JWT 过期时间 `expires_at` 例外，为秒级时间戳整数。

## 6. 资金与积分口径（务必与产品文案一致）

| 概念 | 字段 | 说明 |
|---|---|---|
| 余额（本金） | `balance` | 充值实付部分，可用于全部商品 |
| 赠金 | `gift_balance` | 充值/兑换赠送，**按批次记录、可过期**，仅限店内酒水小吃，不可转赠、交易、提现 |
| 记分牌（积分） | `point` | 当前可用记分牌 |
| 累计记分牌 | `total_point` | 只增不减，排行榜依据 |

- 记分牌兑换赠金比例：**记分牌 : 赠金 = 300 : 1**（300 记分牌 = 1 元赠金），由配置 `point.point_to_gift_rate` 控制。
- 赠金消耗顺序：按 `expired_at` 由近及远，先到期的先扣。
- 任何余额、赠金、记分牌变动都会写入对应流水表，前端可通过流水接口回溯。

## 7. 模块索引

| 文档 | 模块 | 设计稿 |
|---|---|---|
| [auth.md](auth.md) | 登录授权 | — |
| [home.md](home.md) | 首页、门店信息、排行榜 | `index.png`、`ranking.png` |
| [goods.md](goods.md) | 商品分类、商品、桌号 | `shoping-index.png`、`shoping-checkout-select-table.png` |
| [order.md](order.md) | 结算、下单、支付、订单 | `shoping-checkout.png`、`me-order-list.png` |
| [recharge.md](recharge.md) | 充值套餐、充值下单、支付回调 | `rechage.png` |
| [member.md](member.md) | 个人中心、余额/记分牌/赠金流水 | `me-index.png`、`me-sorce-rows.png`、`me-gift-sorce-rows.png` |
| [exchange.md](exchange.md) | 记分牌兑换 | `me-exchage-sorce.png` |
| [wine.md](wine.md) | 存酒、取酒 | `me-store-drink.png`、`me-store-drink-qrcode.png` |
| [admin.md](admin.md) | 管理后台与店员核销 | — |
| [printer.md](printer.md) | 接单打印机配置与自动出单 | — |
