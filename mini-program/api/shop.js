const { get, post } = require('../utils/request');

module.exports = {
  categories: () => get('/api/goods/categories'),
  goods: (params) => get('/api/goods', params),
  goodsDetail: (id) => get(`/api/goods/${id}`),
  tables: () => get('/api/tables'),

  preview: (items) => post('/api/order/preview', { items: JSON.stringify(items) }),
  createOrder: (payload) => post('/api/orders', payload),
  orders: (params) => get('/api/orders', params),
  orderDetail: (id) => get(`/api/orders/${id}`),
  payOrder: (id, payType) => post(`/api/orders/${id}/pay`, { pay_type: payType }),
  cancelOrder: (id) => post(`/api/orders/${id}/cancel`),
};
