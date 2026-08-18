const { login } = require('./api/auth');
const { getToken, setToken, clearToken } = require('./utils/auth');

App({
  globalData: {
    member: null,
  },

  onLaunch() {
    this.ensureLogin();
  },

  /**
   * 静默登录，返回 Promise 供页面在需要登录态时等待
   */
  ensureLogin() {
    if (getToken()) {
      return Promise.resolve(getToken());
    }
    if (this.loginPromise) {
      return this.loginPromise;
    }

    this.loginPromise = new Promise((resolve, reject) => {
      wx.login({
        success: ({ code }) => {
          if (!code) {
            reject(new Error('获取登录凭证失败'));
            return;
          }
          login(code)
            .then((data) => {
              setToken(data.token, data.expires_at);
              this.globalData.member = data.member;
              resolve(data.token);
            })
            .catch(reject);
        },
        fail: reject,
      });
    }).finally(() => {
      this.loginPromise = null;
    });

    return this.loginPromise;
  },

  logout() {
    clearToken();
    this.globalData.member = null;
  },
});
