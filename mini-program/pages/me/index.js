const memberApi = require('../../api/member');
const { updateProfile } = require('../../api/auth');
const { fen2yuan } = require('../../utils/format');

Page({
  data: {
    member: null,
    exchangeVisible: false,
    exchangePoint: '',
  },

  onShow() {
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => {
        this.setData({
          member,
          balanceText: fen2yuan(member.balance),
          giftText: fen2yuan(member.gift_balance),
        });
      })
      .catch(() => {});
  },

  onChooseAvatar(e) {
    updateProfile({ avatar: e.detail.avatarUrl }).then((member) => this.setData({ member }));
  },

  onNicknameChange(e) {
    const nickname = e.detail.value;
    if (!nickname) return;
    updateProfile({ nickname }).then((member) => this.setData({ member }));
  },

  goRecharge() {
    wx.navigateTo({ url: '/pages/recharge/index' });
  },
  goWine() {
    wx.navigateTo({ url: '/pages/wine/index' });
  },
  goWineCode() {
    wx.navigateTo({ url: '/pages/wine/index?autoCode=1' });
  },

  openExchangeModal() {
    this.setData({ exchangeVisible: true, exchangePoint: '' });
  },
  closeExchangeModal() {
    this.setData({ exchangeVisible: false });
  },
  onExchangePointInput(e) {
    this.setData({ exchangePoint: e.detail.value });
  },
  confirmExchange() {
    const point = Number(this.data.exchangePoint);
    if (!point || point <= 0) {
      wx.showToast({ title: '请输入取积分数量', icon: 'none' });
      return;
    }

    memberApi.exchangeByPoint(point).then(() => {
      wx.showToast({ title: '取积分成功' });
      this.setData({ exchangeVisible: false });
      this.onShow();
    });
  },

  go(e) {
    wx.navigateTo({ url: e.currentTarget.dataset.url });
  },
  noop() {},
});


