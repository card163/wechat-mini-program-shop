# 结算与订单

对应设计稿 `shoping-checkout.png`、`shoping-checkout-select-table.png`、`me-order-list.png`。

## 1. 结算预览

`POST /api/order/preview` · 需要会员 token

进入结算页时调用，由服务端计算金额与可用抵扣，**前端不得自行计算价格**。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `items` | array | 是 | 商品数组，JSON 字符串或表单数组 |
| `items[].goods_id` | int | 是 | 商品ID |
| `items[].quantity` | int | 是 | 数量，1-99 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "items": [
      {
        "goods_id": 1,
        "goods_name": "山崎12年",
        "goods_cover": "https://cdn/xx.png",
        "price": 12800,
        "quantity": 2,
        "subtotal": 25600,
        "gift_payable": 1
      }
    ],
    "total_amount": 25600,
    "pay_amount": 25600,
    "gift_payable_amount": 25600,
    "balance": 30000,
    "gift_balance": 5000,
    "balance_enough": true,
    "plan": {
      "pay_gift": 5000,
      "pay_balance": 20600
    }
  }
}
```

| 字段 | 说明 |
|---|---|
| `gift_payable_amount` | 本单中允许使用赠金支付的金额上限（只累计 `gift_payable=1` 的商品小计） |
| `balance_enough` | 本金+赠金是否足够支付本单，`false` 时余额支付按钮置灰 |
| `plan` | 选择余额支付时的扣款预案：**优先扣赠金**，不足部分扣本金 |

| 失败场景 | code | msg |
|---|---|---|
| 商品为空 | 1 | 请先选择商品 |
| 商品已下架 | 1 | 「山崎12年」已下架 |
| 库存不足 | 1 | 「山崎12年」库存不足 |

## 2. 创建订单

`POST /api/orders` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `items` | array | 是 | 同结算预览 |
| `table_id` | int | 是 | 桌号ID |
| `pay_type` | int | 是 | 1 微信支付，2 余额支付 |
| `remark` | string | 否 | 订单备注，最长 200 字符 |

**响应（pay_type=2 余额支付，扣款已在事务内完成）**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "order_id": 101,
    "order_no": "NF20260817160000123456",
    "pay_type": 2,
    "pay_amount": 25600,
    "pay_status": 1,
    "gain_point": 0,
    "pay_params": null
  }
}
```

**响应（pay_type=1 微信支付，需前端调起 `wx.requestPayment`）**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "order_id": 101,
    "order_no": "NF20260817160000123456",
    "pay_type": 1,
    "pay_amount": 25600,
    "pay_status": 0,
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
| 未选桌号 | 1 | 请选择桌号 |
| 桌号无效 | 1 | 桌号不存在 |
| 商品下架/库存不足 | 1 | 同结算预览 |
| 余额不足 | 1 | 余额不足，请先充值 |
| 赠金用于不可用商品 | 1 | 该商品不支持赠金支付 |

**服务端事务约束**（实现必须遵守）

1. 全流程 `BEGIN` 事务，商品行 `SELECT ... FOR UPDATE` 锁库存。
2. 余额支付：会员行加锁，先扣赠金批次（按 `expired_at` 由近及远），再扣本金。
3. 每一笔扣款写入 `nf_member_balance_log`，赠金扣款需记录 `gift_batch_id`。
4. 未支付订单超过配置 `order.auto_cancel_minutes`（默认 15 分钟）由定时任务关闭并回滚库存。

## 3. 订单支付 / 重新支付

`POST /api/orders/{id}/pay` · 需要会员 token

用于待支付订单重新发起支付，或从微信支付改为余额支付。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `pay_type` | int | 是 | 1 微信支付，2 余额支付 |

**响应**：同创建订单。

| 失败场景 | code | msg |
|---|---|---|
| 订单不存在或不属于当前会员 | 404 | 订单不存在 |
| 订单已支付 | 1 | 订单已支付，请勿重复操作 |
| 订单已取消 | 1 | 订单已取消 |

## 4. 订单列表

`GET /api/orders` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `status` | int | 否 | — | 不传为全部；0 待支付，1 已支付待出品，2 已完成，3 已取消 |
| `page` / `page_size` | int | 否 | 1 / 20 | 分页 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 101,
        "order_no": "NF20260817160000123456",
        "table_name": "大桌",
        "total_amount": 25600,
        "pay_amount": 25600,
        "pay_type": 2,
        "pay_status": 1,
        "order_status": 1,
        "order_status_text": "已支付",
        "gain_point": 0,
        "remark": "少冰",
        "created_at": "2026-08-17 16:00:00",
        "paid_at": "2026-08-17 16:00:05",
        "items": [
          { "goods_id": 1, "goods_name": "山崎12年", "goods_cover": "https://cdn/xx.png", "price": 12800, "quantity": 2, "subtotal": 25600 }
        ]
      }
    ],
    "total": 8,
    "page": 1,
    "page_size": 20
  }
}
```

## 5. 订单详情

`GET /api/orders/{id}` · 需要会员 token

**响应**：单个订单对象，结构同列表项，额外包含 `pay_balance`、`pay_gift`、`pay_wechat` 三个支付构成字段。

| 失败场景 | code | msg |
|---|---|---|
| 订单不存在或非本人订单 | 404 | 订单不存在 |

## 6. 取消订单

`POST /api/orders/{id}/cancel` · 需要会员 token

仅待支付订单可取消，取消后回滚库存。

**响应**：`{ "code": 0, "msg": "ok", "data": {} }`

| 失败场景 | code | msg |
|---|---|---|
| 订单已支付 | 1 | 订单已支付，无法取消 |
| 订单状态不允许 | 1 | 订单状态已变更，请刷新 |
