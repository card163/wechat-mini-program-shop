# 记分牌兑换

对应设计稿 `me-exchage-sorce.png`。

## 1. 取积分（弹窗，按数量折算赠金）

`POST /api/exchange/points` · 需要会员 token

对应"我的"页「取积分」弹窗：会员输入任意积分数量，按比例折算为赠金，多余不足一份比例的积分不扣除。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `point` | int | 是 | 取积分数量，需 ≥ `rate`（当前 300） |

**响应**

```json
{
  "code": 0,
  "msg": "取积分成功",
  "data": {
    "consume_point": 300,
    "gift_amount": 100,
    "point_left": 900,
    "gift_balance": 100,
    "batch_id": 12
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 数量为空/非正整数 | 1 | 请输入取积分数量 |
| 不足最小兑换比例 | 1 | 最少需要 300 积分才能兑换 |
| 积分不足 | 1 | 积分不足 |

## 2. 兑换商品列表（备用：物品兑换目录）

`GET /api/exchange/goods` · 无需鉴权（带 token 时返回 `point` 便于置灰按钮）

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "point": 1500,
    "rate": 300,
    "list": [
      {
        "id": 1,
        "type": 2,
        "name": "1元赠金",
        "cover": "",
        "point": 300,
        "gift_amount": 100,
        "gift_expire_days": 90,
        "stock": -1,
        "description": "300记分牌兑换1元赠金"
      },
      {
        "id": 2,
        "type": 1,
        "name": "精酿啤酒",
        "cover": "https://cdn/beer.png",
        "point": 2000,
        "gift_amount": 0,
        "stock": 50,
        "description": "凭兑换码到吧台核销"
      }
    ]
  }
}
```

| 字段 | 说明 |
|---|---|
| `type` | 1 实物/酒水（需店员核销），2 赠金（即时到账） |
| `rate` | 记分牌兑换赠金比例，固定 300（记分牌 : 赠金 = 300 : 1） |
| `stock` | `-1` 表示不限量 |

## 3. 提交兑换（物品兑换目录）

`POST /api/exchange` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `goods_id` | int | 是 | 兑换商品ID |

**响应（type=2 赠金，立即到账）**

```json
{
  "code": 0,
  "msg": "兑换成功",
  "data": {
    "record_id": 88,
    "record_no": "EX20260817161000123456",
    "type": 2,
    "point": 300,
    "gift_amount": 100,
    "status": 1,
    "point_left": 1200,
    "gift_balance": 5100
  }
}
```

**响应（type=1 实物，生成待核销兑换码）**

```json
{
  "code": 0,
  "msg": "兑换成功，请到吧台出示兑换码",
  "data": {
    "record_id": 89,
    "record_no": "EX20260817161200123456",
    "type": 1,
    "point": 2000,
    "status": 0,
    "point_left": 1200
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 商品不存在或已下架 | 1 | 兑换商品不存在 |
| 记分牌不足 | 1 | 记分牌不足 |
| 库存不足 | 1 | 该商品已兑完 |

**服务端事务约束**

1. 会员行 `SELECT ... FOR UPDATE`，校验并扣减记分牌，写 `nf_member_point_log`。
2. `type=2`：新建赠金批次（`source_type=2`），同步 `gift_balance`，写余额流水（`biz_type=5`）。
3. `type=1`：`nf_exchange_record.status=0` 待核销，`stock` 递减、`exchanged` 递增。
4. 记分牌与赠金换算严格按 `rate`，禁止前端传入金额。

## 4. 兑换记录

`GET /api/exchange/records` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `status` | int | 否 | — | 0 待核销、1 已核销、2 已取消 |
| `page` / `page_size` | int | 否 | 1 / 20 | 分页 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 89,
        "record_no": "EX20260817161200123456",
        "goods_name": "精酿啤酒",
        "type": 1,
        "point": 2000,
        "gift_amount": 0,
        "status": 0,
        "status_text": "待核销",
        "verified_at": null,
        "created_at": "2026-08-17 16:12:00"
      }
    ],
    "total": 3,
    "page": 1,
    "page_size": 20
  }
}
```

## 5. 兑换码

`GET /api/exchange/records/{id}/code` · 需要会员 token

返回待核销记录的核销码内容，前端用 `weapp-qrcode` 之类组件本地渲染二维码。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "record_no": "EX20260817161200123456",
    "goods_name": "精酿啤酒",
    "status": 0
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 记录不存在或非本人 | 404 | 兑换记录不存在 |
| 已核销 | 1 | 该兑换已核销 |
