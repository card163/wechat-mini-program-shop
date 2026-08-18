const { home } = require('../../api/auth');
const { money } = require('../../utils/format');

Page({
  data: {
    shop: {},
    banners: [],
    member: null,
    loading: true,
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
          banners: data.banners,
          member: data.member,
          balanceText: data.member ? money(data.member.balance) : '0',
          giftText: data.member ? money(data.member.gift_balance) : '0',
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },

  goShop() {
    wx.switchTab({ url: '/pages/shop/index' });
  },
  goRecharge() {
    wx.navigateTo({ url: '/pages/recharge/index' });
  },
  goWine() {
    wx.navigateTo({ url: '/pages/wine/index' });
  },
  goExchange() {
    wx.navigateTo({ url: '/pages/me/exchange' });
  },
  goRanking() {
    wx.switchTab({ url: '/pages/ranking/index' });
  },
  onBannerTap(e) {
    const { link } = e.currentTarget.dataset;
    if (link) wx.navigateTo({ url: link });
  },
  callShop() {
    if (this.data.shop.phone) {
      wx.makePhoneCall({ phoneNumber: this.data.shop.phone });
    }
  },
});
