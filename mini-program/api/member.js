const { get, post } = require('../utils/request');

module.exports = {
  info: () => get('/api/member/info'),
  balanceLogs: (params) => get('/api/member/balance-logs', params),
  drinkCardLogs: (params) => get('/api/member/drink-card-logs', params),

  rechargePackages: () => get('/api/recharge/packages'),
  createRecharge: (packageId) => post('/api/recharge/orders', { package_id: packageId }),
  rechargeDetail: (id) => get(`/api/recharge/orders/${id}`),
  rechargeOrders: (params) => get('/api/recharge/orders', params),

  wineStorages: (params) => get('/api/wine/storages', params),
  wineStoreCode: () => get('/api/wine/store-code'),
  wineTake: (id, quantity) => post(`/api/wine/storages/${id}/take`, { quantity }),
  wineTakes: (params) => get('/api/wine/takes', params),
  cancelWineTake: (id) => post(`/api/wine/takes/${id}/cancel`),
};
