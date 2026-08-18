const { home } = require('../../api/auth');

Page({
  data: {
    shop: {},
    member: null,
    loading: true,
    noticeVisible: false,
  },

  onShow() {
    this.loadData();
  },

  onPullDownRefresh() {
    this.loadData().finally(() => wx.stopPullDownRefresh());
  },

  loadData() {
    const app = getApp();
    return app
      .ensureLogin()
      .catch(() => null)
      .then(() => home())
      .then((data) => {
        this.setData({
          shop: data.shop,
          member: data.member,
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },

  goShop() {
    wx.navigateTo({ url: '/pages/shop/index' });
  },
  goRecharge() {
    wx.navigateTo({ url: '/pages/recharge/index' });
  },
  showNotice() {
    this.setData({ noticeVisible: true });
  },
  hideNotice() {
    this.setData({ noticeVisible: false });
  },
  noop() {},
});

