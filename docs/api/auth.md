# 登录授权

## 1. 小程序登录

`POST /api/auth/login` · 无需鉴权

用 `wx.login` 获取的 code 换取 openid，首次登录自动注册会员。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `code` | string | 是 | `wx.login` 返回的临时登录凭证 |

**响应**

```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "expires_at": 1787558400,
    "is_new": false,
    "member": {
      "id": 1,
      "nickname": "",
      "avatar": "",
      "phone": "",
      "balance": 0,
      "gift_balance": 0,
      "point": 0
    }
  }
}
```

| 失败场景 | code | msg |
|---|---|---|
| code 为空 | 1 | 缺少登录凭证 |
| 微信接口返回错误 | 1 | 微信登录失败，请重试 |
| 账号被禁用 | 403 | 账号已被禁用 |

## 2. 更新会员资料

`POST /api/auth/profile` · 需要会员 token

用于 `wx.getUserProfile` / 头像昵称填写能力回填。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `nickname` | string | 否 | 昵称，最长 32 字符 |
| `avatar` | string | 否 | 头像地址 |

**响应**：同登录响应中的 `member` 结构。

## 3. 绑定手机号

`POST /api/auth/phone` · 需要会员 token

解密 `button open-type="getPhoneNumber"` 返回的数据。

**请求**

| 参数 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `code` | string | 是 | 新版 `getPhoneNumber` 返回的 `code` |

**响应**

```json
{ "code": 0, "msg": "ok", "data": { "phone": "138****8000" } }
```

| 失败场景 | code | msg |
|---|---|---|
| 解密失败 | 1 | 手机号获取失败，请重试 |

## 4. 退出登录

`POST /api/auth/logout` · 需要会员 token

服务端不维护会话状态，本接口仅用于记录，前端应同时清除本地 token。

**响应**：`{ "code": 0, "msg": "ok", "data": {} }`
