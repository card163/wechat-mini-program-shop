# 个人中心与流水

对应设计稿 `me-index.png`、`me-sorce-rows.png`、`me-gift-sorce-rows.png`。

## 1. 个人中心信息

`GET /api/member/info` · 需要会员 token

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "id": 1,
    "nickname": "牌友",
    "avatar": "",
    "phone": "138****8000",
    "balance": 30000,
    "gift_balance": 5000,
    "point": 1500,
    "total_point": 8600,
    "total_recharge": 100000,
    "total_consume": 70000,
    "order_count": { "unpaid": 1, "paid": 2 },
    "wine_count": 3
  }
}
```

| 字段 | 说明 |
|---|---|
| `order_count.unpaid` | 待支付订单数，用于角标 |
| `order_count.paid` | 已支付待出品订单数 |
| `wine_count` | 存放中的存酒条数 |

## 2. 余额流水（本金）

`GET /api/member/balance-logs` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `page` / `page_size` | int | 否 | 1 / 20 | 分页 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 900,
        "amount": -25600,
        "after_balance": 4400,
        "biz_type": 3,
        "biz_type_text": "消费",
        "biz_no": "NF20260817160000123456",
        "remark": "点单消费",
        "created_at": "2026-08-17 16:00:05"
      }
    ],
    "total": 12,
    "page": 1,
    "page_size": 20
  }
}
```

`biz_type` 取值：1 充值、2 充值赠送、3 消费、4 退款、5 记分牌兑换、6 赠金过期、7 管理员调整。

## 3. 记分牌流水

`GET /api/member/point-logs` · 需要会员 token

对应设计稿 `me-sorce-rows.png`。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 300,
        "point": -300,
        "after_point": 1200,
        "biz_type": 3,
        "biz_type_text": "兑换赠金",
        "remark": "300记分牌兑换1元赠金",
        "created_at": "2026-08-17 16:10:00"
      }
    ],
    "total": 20,
    "page": 1,
    "page_size": 20
  }
}
```

`biz_type` 取值：1 店内存记分牌、2 消费获得、3 兑换赠金、4 兑换商品、5 管理员调整、6 订单退款回滚。

## 4. 赠金记录

`GET /api/member/gift-batches` · 需要会员 token

对应设计稿 `me-gift-sorce-rows.png`，按批次展示发放金额、剩余与有效期。

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `status` | int | 否 | — | 1 有效、2 已用完、3 已过期；不传为全部 |
| `page` / `page_size` | int | 否 | 1 / 20 | 分页 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "summary": {
      "gift_balance": 5000,
      "expiring_amount": 2000,
      "expiring_at": "2026-09-01 00:00:00"
    },
    "list": [
      {
        "id": 7,
        "amount": 20000,
        "used_amount": 15000,
        "remain_amount": 5000,
        "source_type": 1,
        "source_type_text": "充值赠送",
        "status": 1,
        "status_text": "有效",
        "expired_at": "2026-09-01 00:00:00",
        "created_at": "2026-06-03 20:11:00"
      }
    ],
    "total": 4,
    "page": 1,
    "page_size": 20
  }
}
```

| 字段 | 说明 |
|---|---|
| `summary.expiring_amount` | 最近一个即将过期批次的剩余赠金，用于页面顶部提醒；无则为 0 |
| `expired_at` | 为 `null` 表示永久有效 |

**赠金规则提示文案**（前端固定展示，来自设计稿）：赠金仅限兑换店内酒水小吃，不可转赠、不可交易、不可提现。

## 5. 赠金使用明细

`GET /api/member/gift-logs` · 需要会员 token

只返回 `account_type=2`（赠金账户）的流水，结构同余额流水，额外带 `gift_batch_id`。
