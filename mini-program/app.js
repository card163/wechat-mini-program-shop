const { login, shopInfo } = require('./api/auth');
const { getToken, setToken, clearToken } = require('./utils/auth');

App({
  globalData: {
    member: null,
    // 赠金展示配置，未加载完成前用默认值兜底，避免页面短暂闪烁
    giftConfig: { displayName: '赠金', unit: '元' },
    // 饮品卡展示配置，未加载完成前用默认值兜底
    drinkCardConfig: { displayName: '饮品卡', unit: '元' },
  },

  onLaunch() {
    this.ensureLogin();
    this.loadGiftConfig();
  },

  /**
   * 拉取赠金/饮品卡展示名称与计量单位（后台可配置，如改名"酒水卡"），无需登录态，失败则保留默认值
   */
  loadGiftConfig() {
    shopInfo()
      .then((shop) => {
        if (shop && shop.gift) {
          this.globalData.giftConfig = {
            displayName: shop.gift.display_name || '赠金',
            unit: shop.gift.unit || '元',
          };
        }
        if (shop && shop.drink_card) {
          this.globalData.drinkCardConfig = {
            displayName: shop.drink_card.display_name || '饮品卡',
            unit: shop.drink_card.unit || '元',
          };
        }
      })
      .catch(() => {});
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
