# 接单打印机

对接支持云打印的第三方小票打印机厂商：**飞鹅云打印**（vendor=1）、**芯烨云打印**（vendor=2）、**商米云打印**（vendor=3）。
会员支付成功后（微信支付回调 / 余额支付）自动向已启用的打印机推送小票打印任务，打印机联网状态下会自动出票并触发提醒音（飞鹅/芯烨支持语音提醒次数配置）。

## 一、打印机配置（管理后台，仅超级管理员）

### 1. 打印机列表

`GET /admin/printers`

支持分页；`data.list[].secret_key` 返回时做掩码处理（仅显示末 4 位，如 `****ab12`），编辑时留空表示不修改密钥。

### 2. 新增 / 编辑打印机

`POST /admin/printers` · `PUT /admin/printers/{id}`

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `name` | string | 是 | 打印机备注名称，如「出单台1」 |
| `vendor` | int | 是 | 1 飞鹅云打印，2 芯烨云打印，3 商米云打印 |
| `sn` | string | 是 | 打印机终端编号（厂商后台获取） |
| `account` | string | 是 | 开放平台账号：飞鹅 User / 芯烨 User / 商米 AppId |
| `secret_key` | string | 新增必填，编辑留空不修改 | 密钥：飞鹅 UKEY / 芯烨 UKEY / 商米 AppSecret |
| `copies` | int | 否，默认 1 | 打印联数 |
| `voice_times` | int | 否，默认 0 | 语音提醒次数，0 为不提醒（仅飞鹅/芯烨支持，商米忽略） |
| `status` | int | 否，默认 1 | 1 启用（参与自动出单），0 停用 |
| `sort` | int | 否 | 排序 |
| `remark` | string | 否 | 备注 |

### 3. 删除打印机

`DELETE /admin/printers/{id}`

### 4. 测试打印

`POST /admin/printers/{id}/test-print`

向该打印机推送一张测试小票（不关联真实订单，`order_id=0`），用于验证配置是否正确、打印机是否在线。

**响应**

```json
{ "code": 0, "msg": "ok", "data": { "success": true, "message": "" } }
```

`success=false` 时 `message` 为厂商返回的失败原因（如密钥错误、打印机离线）。

## 二、打印日志

`GET /admin/print-logs` · 支持 `printer_id` / `order_no` / `status` 筛选，分页

```json
{
  "list": [
    {
      "id": 1,
      "printer_id": 1,
      "printer_name": "出单台1",
      "order_id": 101,
      "order_no": "NF20260817160000123456",
      "vendor": 1,
      "status": 1,
      "status_text": "成功",
      "third_no": "20260817160000",
      "fail_reason": "",
      "created_at": "2026-08-17 16:00:05"
    }
  ],
  "total": 1,
  "page": 1,
  "page_size": 20
}
```

| status | 含义 |
|---|---|
| 0 | 待打印（异常中断，理论上不应停留） |
| 1 | 成功 |
| 2 | 失败 |

## 三、订单补打印

`POST /admin/orders/{id}/print` · 店员可用

订单支付后向全部已启用打印机重新推送一次打印任务，用于打印机掉线恢复后补单，或新增打印机后补打历史订单。仅已支付订单可补打印。

| 失败场景 | code | msg |
|---|---|---|
| 订单不存在 | 404 | 订单不存在 |
| 订单未支付 | 1 | 订单未支付，无法打印 |

**响应**

```json
{ "code": 0, "msg": "ok", "data": { "success": 2, "failed": 0 } }
```

`success` / `failed` 为本次推送到各打印机的成功 / 失败数量。

## 四、自动出单触发时机（内部实现约束）

1. 订单余额支付成功（`POST /api/orders` 或 `POST /api/orders/{id}/pay` 选择余额支付）。
2. 微信支付回调确认支付成功（`POST /api/notify/wechat/pay`）。
3. 打印动作在支付事务**提交之后**异步触发（不占用会员/库存行锁），任一打印机推送失败不影响支付流程，失败详情写入 `nf_print_log` 供后台核对与补打印。
4. 打印内容包含：门店名称、订单号、桌号、下单时间、商品明细（名称 x 数量）、合计金额、订单备注；用户可控文本（桌号、商品名、备注）会剥离 `<` `>` 字符，避免破坏厂商小票标签指令。

## 五、厂商说明

| vendor | 厂商 | 云打印 API | 备注 |
|---|---|---|---|
| 1 | 飞鹅云打印 | `https://api.feieyun.cn/Api/Open/` | 签名 `sha1(user+ukey+stime)` |
| 2 | 芯烨云打印 | `http://open.xpyun.net/api/openapi/xprinter/print` | 签名 `md5(user+ukey+timestamp)` 大写 |
| 3 | 商米云打印 | 商米开放平台 OAuth2 | **上线前需对照商米开放平台最新文档核实 endpoint 与鉴权参数**，本实现按其公开的 AppId/AppSecret 换取 access_token 后调用打印接口的通用流程编写 |

只选型支持云打印（联网直连厂商服务器）的型号，不支持局域网直连/蓝牙直连打印。
