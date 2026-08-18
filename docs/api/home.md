# 首页与排行榜

## 1. 首页聚合

`GET /api/home` · 无需鉴权（带 token 时额外返回会员摘要）

一次性返回首页所需的门店信息、轮播图与会员摘要，减少小程序端请求数。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "shop": {
      "name": "Nice Fold 德州酒馆",
      "phone": "",
      "address": "",
      "notice": "",
      "business_hours": ""
    },
    "banners": [
      { "id": 1, "title": "", "image": "https://cdn/xx.png", "link": "/pages/shop/index" }
    ],
    "member": {
      "id": 1,
      "nickname": "",
      "avatar": "",
      "balance": 10000,
      "gift_balance": 2000,
      "point": 1500
    }
  }
}
```

未登录时 `member` 为 `null`。

## 2. 门店信息

`GET /api/shop/info` · 无需鉴权

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "name": "Nice Fold 德州酒馆",
    "phone": "",
    "address": "",
    "notice": "",
    "business_hours": ""
  }
}
```

## 3. 排行榜

`GET /api/ranking` · 无需鉴权（带 token 时返回 `me` 字段）

按累计记分牌 `total_point` 倒序排列。

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `limit` | int | 否 | 50 | 榜单条数，最大 100 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      { "rank": 1, "member_id": 12, "nickname": "Player", "avatar": "", "total_point": 98000 }
    ],
    "me": { "rank": 37, "member_id": 1, "nickname": "我", "avatar": "", "total_point": 1500 }
  }
}
```

- 昵称为空时后端返回默认值 `牌友`，前端不再兜底。
- `me.rank` 为 `0` 表示未上榜（累计记分牌为 0）。
- 未登录时 `me` 为 `null`。
