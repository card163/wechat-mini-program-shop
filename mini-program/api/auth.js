const { get, post } = require('../utils/request');

module.exports = {
  login: (code) => post('/api/auth/login', { code }, { auth: false }),
  updateProfile: (data) => post('/api/auth/profile', data),
  bindPhone: (code) => post('/api/auth/phone', { code }),
  logout: () => post('/api/auth/logout'),
  home: () => get('/api/home'),
  shopInfo: () => get('/api/shop/info'),
  ranking: (limit = 50) => get('/api/ranking', { limit }),
};
