# 充值

对应设计稿 `rechage.png`。

## 1. 充值套餐列表

`GET /api/recharge/packages` · 无需鉴权

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": [
    {
      "id": 1,
      "title": "充1000送200",
      "amount": 100000,
      "gift_amount": 20000,
      "gift_point": 0,
      "gift_expire_days": 90
    }
  ]
}
```

| 字段 | 说明 |
|---|---|
| `amount` | 实付金额（分），到账**本金** |
| `gift_amount` | 赠送金额（分），到账**赠金**，单独批次 |
| `gift_point` | 赠送记分牌 |
| `gift_expire_days` | 赠金有效天数，`0` 表示永久有效 |

## 2. 发起充值

`POST /api/recharge/orders` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `package_id` | int | 是 | 充值套餐ID |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "recharge_id": 55,
    "order_no": "RC20260817160500123456",
    "amount": 100000,
    "pay_params": {
      "timeStamp": "1786953140",
      "nonceStr": "5K8264ILTKCH16CQ2502SI8ZNMTM67VS",
      "package": "prepay_id=wx201410272009395522657a690389285100",
      "signType": "RSA",
      "paySign": "oR9d8PuhnIc+YZ8cB..."
    }
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 套餐不存在或已停用 | 1 | 充值套餐不存在 |

**支付成功后服务端行为**（在同一事务内完成）

1. `nf_recharge_order.pay_status` 置 1。
2. 本金入账：`nf_member.balance += amount`，写 `nf_member_balance_log`（`biz_type=1`）。
3. 赠金入账：新建 `nf_member_gift_batch` 批次（`source_type=1`，按 `gift_expire_days` 计算 `expired_at`），同步 `nf_member.gift_balance`，写 `nf_member_balance_log`（`biz_type=2`，带 `gift_batch_id`）。
4. 赠送记分牌（若有）：写 `nf_member_point_log`。
5. 累计充值 `total_recharge += amount`。

## 3. 查询充值结果

`GET /api/recharge/orders/{id}` · 需要会员 token

小程序 `wx.requestPayment` 成功回调后轮询本接口确认到账（以服务端回调为准，不信任前端结果）。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "id": 55,
    "order_no": "RC20260817160500123456",
    "amount": 100000,
    "gift_amount": 20000,
    "gift_point": 0,
    "pay_status": 1,
    "paid_at": "2026-08-17 16:05:20"
  }
}
```

## 4. 充值记录

`GET /api/recharge/orders` · 需要会员 token

**请求**：`page`、`page_size`。

**响应**：分页结构，列表项同上。

## 5. 微信支付回调

`POST /api/notify/wechat/pay` · 无鉴权，由微信服务器调用

**约束**

- 必须验签（V3 平台证书 / 公钥），验签失败返回 `401` 且不处理业务。
- 报文先落 `nf_pay_notify_log` 再处理业务，便于对账排查。
- **必须幂等**：以 `transaction_id` + 商户订单号判重，重复回调直接返回成功。
- 金额需与本地订单金额比对，不一致则记录告警且不入账。
- 处理成功返回：

```json
{ "code": "SUCCESS", "message": "成功" }
```

处理失败返回 HTTP 500 与 `{"code":"FAIL","message":"失败"}`，由微信重试。

商户订单号前缀区分业务：`NF` 开头为点单订单，`RC` 开头为充值订单。
