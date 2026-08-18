const { get, post } = require('../utils/request');

module.exports = {
  info: () => get('/api/member/info'),
  balanceLogs: (params) => get('/api/member/balance-logs', params),
  giftLogs: (params) => get('/api/member/gift-logs', params),
  pointLogs: (params) => get('/api/member/point-logs', params),
  giftBatches: (params) => get('/api/member/gift-batches', params),

  rechargePackages: () => get('/api/recharge/packages'),
  createRecharge: (packageId) => post('/api/recharge/orders', { package_id: packageId }),
  rechargeDetail: (id) => get(`/api/recharge/orders/${id}`),
  rechargeOrders: (params) => get('/api/recharge/orders', params),

  exchangeGoods: () => get('/api/exchange/goods'),
  exchange: (goodsId) => post('/api/exchange', { goods_id: goodsId }),
  exchangeByPoint: (point) => post('/api/exchange/points', { point }),
  exchangeRecords: (params) => get('/api/exchange/records', params),
  exchangeCode: (id) => get(`/api/exchange/records/${id}/code`),

  wineStorages: (params) => get('/api/wine/storages', params),
  wineStoreCode: () => get('/api/wine/store-code'),
  wineTake: (id, quantity) => post(`/api/wine/storages/${id}/take`, { quantity }),
  wineTakes: (params) => get('/api/wine/takes', params),
  cancelWineTake: (id) => post(`/api/wine/takes/${id}/cancel`),
};
