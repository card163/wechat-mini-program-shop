# 存酒与取酒

对应设计稿 `me-store-drink.png`、`me-store-drink-qrcode.png`。

## 业务流程

```
会员出示存酒码（小程序码，scene=会员标识）
      ↓
店员用管理端扫码 → 登记酒名/数量 → 生成存酒记录
      ↓
会员在「我的存酒」发起取酒 → 生成取酒码（限时有效）
      ↓
店员扫取酒码核销 → 扣减剩余数量
```

会员端不能自行新增或核销存酒记录，全部由店员操作，保证账实一致。

## 1. 存酒列表

`GET /api/wine/storages` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `status` | int | 否 | — | 1 存放中、2 已取完、3 已过期；不传为全部 |
| `page` / `page_size` | int | 否 | 1 / 20 | 分页 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 12,
        "wine_name": "麦卡伦12年",
        "spec": "700ml",
        "unit": "瓶",
        "total_qty": 2,
        "remain_qty": 1,
        "images": ["https://cdn/wine.png"],
        "status": 1,
        "status_text": "存放中",
        "stored_at": "2026-07-01 21:30:00",
        "expired_at": "2026-09-29 21:30:00",
        "remark": ""
      }
    ],
    "total": 3,
    "page": 1,
    "page_size": 20
  }
}
```

`expired_at` 为 `null` 表示不过期；已过期记录由定时任务将 `status` 置 3。

## 2. 存酒码

`GET /api/wine/store-code` · 需要会员 token

对应设计稿 `me-store-drink-qrcode.png`。返回带 `scene` 的小程序码，供店员扫码登记。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "scene": "s=1&t=1786953140&k=8f3a...",
    "qrcode": "data:image/png;base64,iVBORw0KG...",
    "expires_at": 1786953740
  }
}
```

| 字段 | 说明 |
|---|---|
| `scene` | 小程序码携带的场景值，含会员标识、时间戳与签名 |
| `qrcode` | 小程序码图片，base64 或 CDN 地址 |
| `expires_at` | 场景值失效时间戳，默认 10 分钟，过期需重新获取 |

## 3. 发起取酒

`POST /api/wine/storages/{id}/take` · 需要会员 token

生成待核销取酒码，不直接扣减库存。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `quantity` | int | 是 | 取出数量，1 ≤ quantity ≤ `remain_qty` |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "take_id": 30,
    "take_no": "WT20260817162000123456",
    "wine_name": "麦卡伦12年",
    "quantity": 1,
    "status": 0,
    "code_expired_at": "2026-08-17 16:30:00"
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 存酒记录不存在或非本人 | 404 | 存酒记录不存在 |
| 数量非法 | 1 | 取酒数量不正确 |
| 剩余不足 | 1 | 剩余数量不足 |
| 已有未核销取酒码 | 1 | 已有待核销的取酒码，请先使用 |
| 记录已过期 | 1 | 该存酒已过期，请联系店员 |

## 4. 取酒记录

`GET /api/wine/takes` · 需要会员 token

**请求**：`status`（0 待核销、1 已核销、2 已失效）、`page`、`page_size`。

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "list": [
      {
        "id": 30,
        "take_no": "WT20260817162000123456",
        "storage_id": 12,
        "wine_name": "麦卡伦12年",
        "quantity": 1,
        "status": 1,
        "status_text": "已核销",
        "verified_at": "2026-08-17 16:25:00",
        "created_at": "2026-08-17 16:20:00"
      }
    ],
    "total": 5,
    "page": 1,
    "page_size": 20
  }
}
```

## 5. 取消取酒码

`POST /api/wine/takes/{id}/cancel` · 需要会员 token

仅待核销状态可取消，取消后置为已失效。

**响应**：`{ "code": 0, "msg": "ok", "data": {} }`

| 失败场景 | code | msg |
|---|---|---|
| 已核销 | 1 | 该取酒码已核销 |
