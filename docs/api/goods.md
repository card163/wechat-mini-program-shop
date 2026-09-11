# 商品与桌号

## 1. 商品分类

`GET /api/goods/categories` · 无需鉴权

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": [
    { "id": 1, "name": "威士忌", "icon": "" }
  ]
}
```

仅返回 `status=1` 的分类，按 `sort` 升序、`id` 升序。

## 2. 商品列表

`GET /api/goods` · 无需鉴权

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `category_id` | int | 否 | 0 | 分类ID，0 或不传表示全部 |
| `keyword` | string | 否 | — | 商品名称模糊搜索 |
| `page` | int | 否 | 1 | 页码 |
| `page_size` | int | 否 | 20 | 每页条数 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 1,
        "category_id": 1,
        "name": "山崎12年",
        "subtitle": "单杯 30ml",
        "cover": "https://cdn/xx.png",
        "price": 12800,
        "origin_price": 15800,
        "unit": "杯",
        "stock": -1,
        "sales": 32,
        "gift_payable": 1
      }
    ],
    "total": 36,
    "page": 1,
    "page_size": 20
  }
}
```

- `stock` 为 `-1` 表示不限库存。
- `gift_payable=1` 表示该商品可用赠金支付；为 `0` 时结算页不得把赠金计入抵扣。
- 管理后台维护商品时，`gift_payable=1` 还需填写 `gift_amount`（兑换该商品单件固定消耗的赠金数量，与现金售价无关，`gift_payable` 关闭时可不填/忽略），结算抵扣仍由 `/api/order/preview` 与 `/api/orders` 服务端自动计算。
- 「赠金」的对外展示名称与计量单位（元/张）可在管理后台「系统设置-礼品卡与赠金」配置，参见 [home.md](./home.md) 的 `gift` 字段。
- 管理后台维护商品时可额外配置 `drink_card_gift_amount`（购买该商品单件自动赠送的饮品卡数量，0 表示不赠送）与 `drink_card_gift_expire_days`（赠送的饮品卡有效天数，0 表示永久有效）。顾客下单支付成功（微信支付/余额支付均适用）后，服务端按 `drink_card_gift_amount × 购买数量` 自动发放一批饮品卡到会员账户，与该商品是否支持“饮品卡抵扣支付”（`drink_card_payable`/`drink_card_amount`）互不影响。
- `gift_amount`/`drink_card_amount`/`drink_card_gift_amount`/`drink_card_gift_expire_days` 这 4 个字段**只在商品详情接口** `GET /api/goods/{id}` 暴露（供小程序点单页底部详情抽屉展示“可用赠金/饮品卡支付需消耗多少”“购买赠送多少饮品卡”），商品列表接口 `GET /api/goods` 不返回，减少列表接口体积。

## 3. 商品详情

`GET /api/goods/{id}` · 无需鉴权

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "id": 1,
    "category_id": 1,
    "name": "山崎12年",
    "subtitle": "单杯 30ml",
    "cover": "https://cdn/xx.png",
    "images": ["https://cdn/1.png"],
    "price": 12800,
    "origin_price": 15800,
    "unit": "杯",
    "stock": -1,
    "sales": 32,
    "gift_payable": 1,
    "drink_card_payable": 0,
    "description": "<p>商品详情</p>",
    "gift_amount": 12800,
    "drink_card_amount": 0,
    "drink_card_gift_amount": 0,
    "drink_card_gift_expire_days": 0
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 商品不存在或已下架 | 404 | 商品不存在或已下架 |

## 4. 桌号列表

`GET /api/tables` · 无需鉴权

对应设计稿 `shoping-checkout-select-table.png`。桌号分「分区」两级管理（先划区、再在区内加具体桌号），返回按分区分组的结构，仅包含启用分区下至少有一个启用桌号的分区；每个分区内的桌号按 `sort` 升序、`id` 升序。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": [
    {
      "zone_id": 1,
      "zone_name": "一号台",
      "tables": [
        { "id": 1, "name": "A01" },
        { "id": 2, "name": "A02" }
      ]
    },
    {
      "zone_id": 2,
      "zone_name": "二号台",
      "tables": [
        { "id": 10, "name": "B01" }
      ]
    }
  ]
}
```

下单时提交的仍是具体桌号的 `table_id`；订单快照的 `table_name` 由服务端拼接为「分区名 桌号名」（如「一号台 A01」）。
