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

  // 使用微信昵称快捷填入时，input 会先触发 bindinput，随后才 blur，
  // 部分机型 blur 事件的 e.detail.value 存在滞后，故以 input 记录的最新值为准
  onNicknameInput(e) {
    this.nicknameDraft = e.detail.value;
  },

  onNicknameReview(e) {
    if (e.detail.pass === false) {
      wx.showToast({ title: '昵称包含违规内容，请修改', icon: 'none' });
    }
  },

  onNicknameChange(e) {
    const nickname = this.nicknameDraft !== undefined ? this.nicknameDraft : e.detail.value;
    this.nicknameDraft = undefined;
    if (!nickname || nickname === this.data.member.nickname) return;
    updateProfile({ nickname })
      .then((member) => {
        this.setData({ member });
        wx.showToast({ title: '昵称已保存' });
      })
      .catch(() => {});
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


