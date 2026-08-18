# 管理后台与店员核销

前缀 `/admin`，除登录外均需管理端 token（`Authorization: Bearer <token>`）。
角色：`1` 超级管理员，`2` 店员。店员仅可访问「店员可用」标记的接口。

## 一、认证（已实现）

### 1. 登录

`POST /admin/auth/login` · 无需鉴权

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `username` | string | 是 | 账号 |
| `password` | string | 是 | 密码 |

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "expires_at": 1786996340,
    "admin": { "id": 1, "username": "admin", "real_name": "超级管理员", "avatar": "", "phone": "", "role": 1 }
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 参数为空 | 1 | 请输入账号 / 请输入密码 |
| 账号或密码错误 | 1 | 账号或密码错误 |
| 账号被禁用 | 403 | 账号已被禁用 |

### 2. 当前登录信息

`GET /admin/auth/profile` · 店员可用 · 返回 `admin` 结构。

### 3. 修改密码

`POST /admin/auth/change-password` · 店员可用

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `old_password` | string | 是 | 原密码 |
| `new_password` | string | 是 | 新密码，6-32 位 |

## 二、店员核销（待实现，优先级最高）

### 1. 扫存酒码 → 解析会员

`POST /admin/wine/scan` · 店员可用

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `scene` | string | 是 | 小程序码 scene 原文 |

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "member": { "id": 1, "nickname": "牌友", "avatar": "", "phone": "138****8000" }
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| 签名不合法 | 1 | 无效的存酒码 |
| 已过期 | 1 | 存酒码已过期，请让会员重新生成 |

### 2. 登记存酒

`POST /admin/wine/storages` · 店员可用

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `member_id` | int | 是 | 会员ID（来自扫码结果） |
| `wine_name` | string | 是 | 酒名 |
| `spec` | string | 否 | 规格 |
| `unit` | string | 否 | 单位，默认「瓶」 |
| `total_qty` | int | 是 | 数量，1-99 |
| `images` | array | 否 | 存酒照片 |
| `expire_days` | int | 否 | 保存天数，默认取配置 `wine.default_expire_days` |
| `remark` | string | 否 | 备注 |

返回新建的存酒记录，同时记录 `store_admin_id`。

### 3. 核销取酒码

`POST /admin/wine/takes/verify` · 店员可用

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `take_no` | string | 是 | 取酒码内容 |

事务内：校验状态与有效期 → `nf_wine_storage.remain_qty -= quantity` → 剩余为 0 时 `status=2` → 取酒记录置已核销。

| 失败场景 | code | msg |
|---|---|---|
| 取酒码不存在 | 1 | 取酒码无效 |
| 已核销 | 1 | 该取酒码已核销 |
| 已过期 | 1 | 取酒码已过期 |

### 4. 核销兑换码

`POST /admin/exchange/verify` · 店员可用

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `record_no` | string | 是 | 兑换单号 |

核销成功后 `status=1`，记录 `verify_admin_id`、`verified_at`。

### 5. 店内发放记分牌

`POST /admin/member/point/adjust` · 仅超级管理员

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `member_id` | int | 是 | 会员ID |
| `point` | int | 是 | 变动值，正数发放、负数扣减 |
| `remark` | string | 是 | 原因，必填以便审计 |

事务内会员行加锁，写 `nf_member_point_log`（`biz_type=1` 或 `5`，`operator_id` 记录操作人）。

## 三、后台管理（待实现）

除特别说明外均为超级管理员权限，列表接口统一支持 `page` / `page_size`。

### 会员

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/admin/members` | 会员列表，支持昵称/手机号搜索、按余额或累计记分牌排序 |
| GET | `/admin/members/{id}` | 会员详情，含余额、赠金批次、记分牌 |
| POST | `/admin/members/{id}/status` | 启用 / 禁用 |
| POST | `/admin/members/{id}/balance/adjust` | 调整本金余额，必填原因，落流水 |
| POST | `/admin/members/{id}/gift/grant` | 发放赠金批次，可设有效期 |
| GET | `/admin/members/{id}/balance-logs` | 余额流水 |
| GET | `/admin/members/{id}/point-logs` | 记分牌流水 |

### 商品与桌号

| 方法 | 路径 | 说明 |
|---|---|---|
| GET/POST | `/admin/goods-categories` | 分类列表 / 新增 |
| PUT/DELETE | `/admin/goods-categories/{id}` | 编辑 / 删除（有商品时禁止删除） |
| GET/POST | `/admin/goods` | 商品列表 / 新增 |
| PUT/DELETE | `/admin/goods/{id}` | 编辑 / 删除 |
| POST | `/admin/goods/{id}/status` | 上下架 |
| GET/POST | `/admin/tables` | 桌号列表 / 新增 |
| PUT/DELETE | `/admin/tables/{id}` | 编辑 / 删除 |

### 订单

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/admin/orders` | 订单列表，支持按状态、桌号、时间区间、订单号筛选 · 店员可用 |
| GET | `/admin/orders/{id}` | 订单详情 · 店员可用 |
| POST | `/admin/orders/{id}/finish` | 标记已完成（出品完成）· 店员可用 |
| POST | `/admin/orders/{id}/refund` | 退款，按原路退回本金/赠金批次，落流水 |
| POST | `/admin/orders/{id}/print` | 补打印小票（推送至全部启用的打印机）· 店员可用，详见 [printer.md](printer.md) |

### 充值与兑换

| 方法 | 路径 | 说明 |
|---|---|---|
| GET/POST | `/admin/recharge-packages` | 充值套餐列表 / 新增 |
| PUT/DELETE | `/admin/recharge-packages/{id}` | 编辑 / 删除 |
| GET | `/admin/recharge-orders` | 充值订单列表 |
| GET/POST | `/admin/exchange-goods` | 兑换商品列表 / 新增 |
| PUT/DELETE | `/admin/exchange-goods/{id}` | 编辑 / 删除 |
| GET | `/admin/exchange-records` | 兑换记录，支持按核销状态筛选 · 店员可用 |

### 存酒

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/admin/wine/storages` | 存酒列表，支持按会员、状态筛选 · 店员可用 |
| PUT | `/admin/wine/storages/{id}` | 修改酒名/数量/有效期，需记录操作人 |
| GET | `/admin/wine/takes` | 取酒记录 · 店员可用 |

### 内容与配置

| 方法 | 路径 | 说明 |
|---|---|---|
| GET/POST | `/admin/banners` | 轮播图列表 / 新增 |
| PUT/DELETE | `/admin/banners/{id}` | 编辑 / 删除 |
| GET | `/admin/settings/{group}` | 读取配置分组（base / point / order / wine） |
| PUT | `/admin/settings/{group}` | 保存配置分组 |
| GET/POST | `/admin/admin-users` | 管理员与店员账号管理 |
| POST | `/admin/upload/image` | 图片上传，返回可访问地址 · 店员可用 |
| GET/POST | `/admin/printers` | 接单打印机列表 / 新增，详见 [printer.md](printer.md) |
| PUT/DELETE | `/admin/printers/{id}` | 编辑 / 删除打印机 |
| POST | `/admin/printers/{id}/test-print` | 测试打印 |
| GET | `/admin/print-logs` | 打印日志 |

### 统计

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/admin/stat/overview` | 今日营业额、订单数、新增会员、充值金额 |
| GET | `/admin/stat/trend` | 近 N 天营业额与订单趋势 |
