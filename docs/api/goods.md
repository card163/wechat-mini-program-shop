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
    "description": "<p>商品详情</p>"
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 商品不存在或已下架 | 404 | 商品不存在或已下架 |

## 4. 桌号列表

`GET /api/tables` · 无需鉴权

对应设计稿 `shoping-checkout-select-table.png`。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": [
    { "id": 1, "name": "大桌" },
    { "id": 2, "name": "小桌" },
    { "id": 3, "name": "一楼" }
  ]
}
```
