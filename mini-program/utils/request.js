const { BASE_URL } = require('../config');
const { getToken, clearToken } = require('./auth');

let relogining = false;

/**
 * 统一请求封装：自动带 token、统一响应体解析与错误提示
 * 页面禁止直接调用 wx.request
 */
function request(options) {
  const { url, method = 'GET', data = {}, showError = true, auth = true } = options;

  return new Promise((resolve, reject) => {
    const header = { 'Content-Type': 'application/x-www-form-urlencoded' };
    const token = getToken();
    if (auth && token) {
      header.Authorization = `Bearer ${token}`;
    }

    wx.request({
      url: BASE_URL + url,
      method,
      data,
      header,
      success: ({ statusCode, data: body }) => {
        if (statusCode !== 200 || !body || typeof body.code === 'undefined') {
          const msg = '网络异常，请稍后再试';
          if (showError) wx.showToast({ title: msg, icon: 'none' });
          reject(new Error(msg));
          return;
        }

        if (body.code === 0) {
          resolve(body.data);
          return;
        }

        if (body.code === 401) {
          clearToken();
          if (!relogining) {
            relogining = true;
            const app = getApp();
            app.ensureLogin().finally(() => {
              relogining = false;
            });
          }
        }

        if (showError) {
          wx.showToast({ title: body.msg || '操作失败', icon: 'none' });
        }
        reject(Object.assign(new Error(body.msg || '操作失败'), { code: body.code }));
      },
      fail: () => {
        const msg = '网络连接失败';
        if (showError) wx.showToast({ title: msg, icon: 'none' });
        reject(new Error(msg));
      },
    });
  });
}

const get = (url, data, options = {}) => request({ url, method: 'GET', data, ...options });
const post = (url, data, options = {}) => request({ url, method: 'POST', data, ...options });

/**
 * 文件上传封装：自动带 token，统一响应体解析与错误提示
 * 用于把 wxfile:// 本地临时文件（如头像）上传到服务器换取可访问 URL
 */
function upload(url, filePath, options = {}) {
  const { name = 'file', showError = true } = options;

  return new Promise((resolve, reject) => {
    const header = {};
    const token = getToken();
    if (token) {
      header.Authorization = `Bearer ${token}`;
    }

    wx.uploadFile({
      url: BASE_URL + url,
      filePath,
      name,
      header,
      success: ({ statusCode, data }) => {
        let body;
        try {
          body = JSON.parse(data);
        } catch (e) {
          body = null;
        }

        if (statusCode !== 200 || !body || typeof body.code === 'undefined') {
          const msg = '网络异常，请稍后再试';
          if (showError) wx.showToast({ title: msg, icon: 'none' });
          reject(new Error(msg));
          return;
        }

        if (body.code === 0) {
          resolve(body.data);
          return;
        }

        if (showError) {
          wx.showToast({ title: body.msg || '上传失败', icon: 'none' });
        }
        reject(Object.assign(new Error(body.msg || '上传失败'), { code: body.code }));
      },
      fail: () => {
        const msg = '网络连接失败';
        if (showError) wx.showToast({ title: msg, icon: 'none' });
        reject(new Error(msg));
      },
    });
  });
}

module.exports = { request, get, post, upload };
