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
| `use_gift` | int | 否 | 默认 1，仅影响响应里 `combo_pay` 字段的折算结果：是否按「微信组合支付」勾选使用酒水卡余额 |
| `use_drink_card` | int | 否 | 默认 1，同上，是否使用饮品卡余额 |

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
    "removed_items": [],
    "total_amount": 25600,
    "pay_amount": 25600,
    "balance": 30000,
    "gift_balance": 5000,
    "drink_card_balance": 0,
    "gift_payable_amount": 25600,
    "drink_card_payable_amount": 0,
    "pay_options": {
      "balance": { "available": true, "usable": true },
      "gift": { "available": true, "usable": true },
      "drink_card": { "available": false, "usable": false }
    },
    "combo_pay": {
      "show": false,
      "gift_units": 0,
      "gift_cash": 0,
      "drink_card_units": 0,
      "drink_card_cash": 0,
      "wechat_amount": 25600
    }
  }
}
```

| 字段 | 说明 |
|---|---|
| `removed_items` | 购物车里已下架/被删除的商品会**自动剔除**、不计入金额，列表返回 `[{goods_id, goods_name}]` 供前端提示用户并同步清理本地购物车缓存；`goods_name` 在商品已被彻底删除时为空字符串。若购物车所有商品都已失效，`items` 为空数组、`total_amount` 为 0 |
| `pay_options` | 微信支付、余额支付、赠金支付、饮品卡支付**四种支付方式各自独立**，不再组合扣款；每种非微信支付方式包含 `available`（本单商品是否都支持该账户全额支付，如管理端关闭了赠金支付开关，或订单里存在不支持该账户的商品，则为 `false`）和 `usable`（`available` 且账户余额足够覆盖整单）。前端据此置灰不可用的支付选项 |
| `gift_payable_amount`/`drink_card_payable_amount` | 若选择对应支付方式，需要消耗的凭证数量（与余额同单位），仅在对应 `pay_options.*.available` 为 `true` 时代表整单可被全额覆盖，供前端展示用 |
| `combo_pay` | 微信支付时的「酒水卡/饮品卡 + 微信」组合支付方案。各账户余额按系统设置里的兑法币汇率（`point.gift_cash_rate`/`point.drink_card_cash_rate`，单位元，0=不开启）折算成现金，且不超过该账户在本单的可抵扣现金上限（与 `pay_options.gift`/`drink_card` 的 `available` 判定同一套规则，即仍需商品开启对应支付开关）；`show` 为 `true` 时表示酒水卡、饮品卡都不足以单独全额支付整单、但至少一方仍有余额，建议前端弹窗询问是否启用组合支付；`gift_units`/`drink_card_units` 是按 `use_gift`/`use_drink_card` 折算后实际会消耗的凭证数量，`gift_cash`/`drink_card_cash` 是对应折算出的现金金额（分），`wechat_amount` 是抵扣后仍需微信支付的金额（分） |

**注意**：预览接口对「商品已下架/已删除」不再报错阻断，而是走 `removed_items` 静默剔除（详见上表）；真正下单 `POST /api/orders` 时如仍传了失效商品，才会报错阻断，见下表。

| 失败场景 | code | msg |
|---|---|---|
| 商品为空 | 1 | 请先选择商品 |
| 库存不足 | 1 | 「山崎12年」库存不足 |

## 2. 创建订单

`POST /api/orders` · 需要会员 token

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `items` | array | 是 | 同结算预览 |
| `table_id` | int | 是 | 桌号ID |
| `pay_type` | int | 是 | 1 微信支付，2 余额(本金)支付，3 酒水卡(赠金)支付，4 饮品卡支付，5 微信+酒水卡/饮品卡组合支付。2/3/4 均为**独立扣款**：本单必须能被该账户全额覆盖才允许使用，不会跟其他账户组合抵扣；5 专用于酒水卡/饮品卡余额都不足以单独支付整单时，按汇率折算部分现金抵扣、不足部分仍由微信补齐 |
| `use_gift` | int | 否 | 仅 `pay_type=5` 时生效，默认 0，1 表示本次组合支付勾选使用酒水卡余额 |
| `use_drink_card` | int | 否 | 仅 `pay_type=5` 时生效，默认 0，1 表示使用饮品卡余额 |
| `remark` | string | 否 | 订单备注，最长 200 字符 |

**响应（pay_type=2/3/4 非微信支付，扣款已在事务内完成）**

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

**响应（pay_type=5 微信组合支付，酒水卡/饮品卡部分已在事务内扣款，`pay_amount` 仍为订单整单金额，微信只需实际支付 `pay_params` 里的差额）**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "order_id": 101,
    "order_no": "NF20260817160000123456",
    "pay_type": 5,
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

若酒水卡+饮品卡折算金额恰好覆盖整单，无需微信补齐差额，订单会直接落地为 `pay_status:1`，`pay_params` 为 `null`（同 pay_type=2/3/4 的响应形态）。

| 失败场景 | code | msg |
|---|---|---|
| 未选桌号 | 1 | 请选择桌号 |
| 桌号无效 | 1 | 桌号不存在 |
| 商品下架/库存不足 | 1 | 同结算预览 |
| 本金余额不足 | 1 | 余额不足，请先充值 |
| 酒水卡不可用（存在不支持商品/管理端已关闭）| 1 | 酒水卡暂不支持支付本单，请选择其他支付方式 |
| 酒水卡余额不足 | 1 | 酒水卡余额不足，请先充值 |
| 饮品卡不可用（存在不支持商品）| 1 | 饮品卡暂不支持支付本单，请选择其他支付方式 |
| 饮品卡余额不足 | 1 | 饮品卡余额不足，请先充值 |
| 组合支付但两种卡都未勾选/余额为0 | 1 | 请至少选择一种可用余额参与组合支付 |

**服务端事务约束**（实现必须遵守）

1. 全流程 `BEGIN` 事务，商品行 `SELECT ... FOR UPDATE` 锁库存。
2. 非微信支付：会员行加锁，仅扣选中的单一账户（本金/赠金/饮品卡），不跨账户组合；组合支付（pay_type=5）则在下单事务内预先扣除酒水卡/饮品卡部分，微信部分等支付回调确认后才真正落地。
3. 每一笔扣款写入 `nf_member_balance_log`，赠金/饮品卡扣款需记录对应批次ID。
4. 未支付订单超过配置 `order.auto_cancel_minutes`（默认 15 分钟）由定时任务关闭并回滚库存；组合支付订单超时自动取消时，已预扣的酒水卡/饮品卡会原路退回。

## 3. 订单支付 / 重新支付

`POST /api/orders/{id}/pay` · 需要会员 token

用于待支付订单重新发起支付，或切换支付方式。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `pay_type` | int | 是 | 同创建订单：1 微信支付，2 余额(本金)支付，3 酒水卡支付，4 饮品卡支付，5 微信组合支付 |
| `use_gift` | int | 否 | 仅 `pay_type=5` 时生效，同创建订单 |
| `use_drink_card` | int | 否 | 仅 `pay_type=5` 时生效，同创建订单 |

**响应**：同创建订单。若订单此前已走过组合支付但微信部分尚未完成，切换/重试任何支付方式前会先自动原路退回之前预扣的酒水卡/饮品卡，再按新选择的方式重新计算。

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
        "daily_no": 5,
        "table_name": "一号台 A01",
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

| 字段 | 说明 |
|---|---|
| `daily_no` | 每日出单序号，每天从1开始递增（跨天重置），用于叫号与打印小票，创建订单时生成 |

## 5. 订单详情

`GET /api/orders/{id}` · 需要会员 token

**响应**：单个订单对象，结构同列表项，额外包含 `pay_balance`、`pay_gift`、`pay_wechat`、`pay_drink_card` 四个支付构成字段（pay_type=5 组合支付时可能同时有多个字段 > 0）。

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
