import { del, get, post, put, type PageResult } from './request'

export interface AdminProfile {
  id: number
  username: string
  real_name: string
  avatar: string
  phone: string
  role: number
}

export const authApi = {
  login: (username: string, password: string) => post('/admin/auth/login', { username, password }),
  profile: () => get<AdminProfile>('/admin/auth/profile'),
  changePassword: (oldPassword: string, newPassword: string) =>
    post('/admin/auth/change-password', { old_password: oldPassword, new_password: newPassword }),
}

export const statApi = {
  overview: () => get('/admin/stat/overview'),
  trend: (days: number) => get('/admin/stat/trend', { days }),
}

export const memberApi = {
  list: (params: Record<string, any>) => get<PageResult<any>>('/admin/members', params),
  detail: (id: number) => get(`/admin/members/${id}`),
  status: (id: number, status: number) => post(`/admin/members/${id}/status`, { status }),
  updatePhone: (id: number, phone: string) => post(`/admin/members/${id}/phone`, { phone }),
  adjustBalance: (id: number, amount: number, remark: string) =>
    post(`/admin/members/${id}/balance/adjust`, { amount, remark }),
  grantGift: (id: number, amount: number, expireDays: number, remark: string) =>
    post(`/admin/members/${id}/gift/grant`, { amount, expire_days: expireDays, remark }),
  adjustPoint: (id: number, point: number, remark: string) =>
    post(`/admin/members/${id}/point/adjust`, { point, remark }),
  balanceLogs: (id: number, params: Record<string, any>) =>
    get<PageResult<any>>(`/admin/members/${id}/balance-logs`, params),
  pointLogs: (id: number, params: Record<string, any>) =>
    get<PageResult<any>>(`/admin/members/${id}/point-logs`, params),
}

export const orderApi = {
  list: (params: Record<string, any>) => get<PageResult<any>>('/admin/orders', params),
  detail: (id: number) => get(`/admin/orders/${id}`),
  finish: (id: number) => post(`/admin/orders/${id}/finish`),
  refund: (id: number, remark: string) => post(`/admin/orders/${id}/refund`, { remark }),
  print: (id: number) => post(`/admin/orders/${id}/print`),
}

/** 后台同构资源的标准 CRUD */
export const crudApi = (prefix: string) => ({
  list: (params: Record<string, any>) => get<PageResult<any>>(prefix, params),
  detail: (id: number) => get(`${prefix}/${id}`),
  create: (data: Record<string, any>) => post(prefix, data),
  update: (id: number, data: Record<string, any>) => put(`${prefix}/${id}`, data),
  remove: (id: number) => del(`${prefix}/${id}`),
})

export const goodsApi = {
  ...crudApi('/admin/goods'),
  changeStatus: (id: number, status: number) => post(`/admin/goods/${id}/status`, { status }),
}
export const categoryApi = crudApi('/admin/goods-categories')
export const tableApi = crudApi('/admin/tables')
export const rechargePackageApi = crudApi('/admin/recharge-packages')
export const exchangeGoodsApi = crudApi('/admin/exchange-goods')
export const bannerApi = crudApi('/admin/banners')

export const verifyApi = {
  scanWine: (scene: string) => post('/admin/wine/scan', { scene }),
  storeWine: (data: Record<string, any>) => post('/admin/wine/storages', data),
  wineStorages: (params: Record<string, any>) => get<PageResult<any>>('/admin/wine/storages', params),
  verifyWineTake: (takeNo: string) => post('/admin/wine/takes/verify', { take_no: takeNo }),
  verifyExchange: (recordNo: string) => post('/admin/exchange/verify', { record_no: recordNo }),
  exchangeRecords: (params: Record<string, any>) => get<PageResult<any>>('/admin/exchange-records', params),
}

export const settingApi = {
  get: (group: string) => get<Record<string, string>>(`/admin/settings/${group}`),
  save: (group: string, data: Record<string, any>) => put(`/admin/settings/${group}`, data),
}

export const adminUserApi = {
  list: (params: Record<string, any>) => get<PageResult<any>>('/admin/admin-users', params),
  create: (data: Record<string, any>) => post('/admin/admin-users', data),
  update: (id: number, data: Record<string, any>) => put(`/admin/admin-users/${id}`, data),
  remove: (id: number) => del(`/admin/admin-users/${id}`),
}

export const printerApi = {
  ...crudApi('/admin/printers'),
  testPrint: (id: number) => post(`/admin/printers/${id}/test-print`),
}

export const printLogApi = {
  list: (params: Record<string, any>) => get<PageResult<any>>('/admin/print-logs', params),
}

export const uploadUrl = '/admin/upload/image'
