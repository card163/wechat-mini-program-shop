const { get, post, upload } = require('../utils/request');

module.exports = {
  login: (code) => post('/api/auth/login', { code }, { auth: false }),
  updateProfile: (data) => post('/api/auth/profile', data),
  bindPhone: (code) => post('/api/auth/phone', { code }),
  logout: () => post('/api/auth/logout'),
  uploadImage: (filePath) => upload('/api/upload/image', filePath),
  home: () => get('/api/home'),
  shopInfo: () => get('/api/shop/info'),
};
