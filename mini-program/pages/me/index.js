const memberApi = require('../../api/member');
const { updateProfile, uploadImage } = require('../../api/auth');
const { fen2yuan, giftLabel, giftAmountText, drinkCardLabel, drinkCardAmountText } = require('../../utils/format');

Page({
  data: {
    member: null,
    showPhoneModal: false,
    giftLabel: '赠金',
    drinkCardLabel: '饮品卡',
  },

  onShow() {
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => {
        this.setData({
          member,
          balanceText: fen2yuan(member.balance),
          giftText: giftAmountText(member.gift_balance),
          giftLabel: giftLabel(),
          drinkCardText: drinkCardAmountText(member.drink_card_balance),
          drinkCardLabel: drinkCardLabel(),
          pointText: String(member.point || 0),
        });
      })
      .catch(() => {});
  },

  // 头像/昵称等资料修改属于"需要登录的操作"，未绑定手机号则弹窗拦截，登录成功后自动重试该操作
  requireLogin(action) {
    if (this.data.member && this.data.member.phone) {
      action();
      return;
    }
    this.pendingAction = action;
    this.setData({ showPhoneModal: true });
  },

  onPhoneBound() {
    memberApi.info().then((member) => {
      this.setData({
        member,
        showPhoneModal: false,
        balanceText: fen2yuan(member.balance),
        giftText: giftAmountText(member.gift_balance),
        giftLabel: giftLabel(),
        drinkCardText: drinkCardAmountText(member.drink_card_balance),
        drinkCardLabel: drinkCardLabel(),
        pointText: String(member.point || 0),
      });
      const action = this.pendingAction;
      this.pendingAction = null;
      if (action) action();
    });
  },

  onChooseAvatar(e) {
    const avatarUrl = e.detail.avatarUrl;
    this.requireLogin(() => {
      // wx.chooseAvatar 拿到的是 wxfile:// 本地临时路径，需先上传到服务器换取可访问 URL
      wx.showLoading({ title: '上传中', mask: true });
      uploadImage(avatarUrl)
        .then(({ url }) => updateProfile({ avatar: url }))
        .then((member) => this.setData({ member }))
        .finally(() => wx.hideLoading());
    });
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
    this.requireLogin(() => {
      updateProfile({ nickname })
        .then((member) => {
          this.setData({ member });
          wx.showToast({ title: '昵称已保存' });
        })
        .catch(() => {});
    });
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
  goBalanceLogs() {
    wx.navigateTo({ url: '/pages/me/balance-logs' });
  },
  goGiftLogs() {
    wx.navigateTo({ url: '/pages/me/gift-logs' });
  },
  goDrinkCardLogs() {
    wx.navigateTo({ url: '/pages/me/drink-card-logs' });
  },
  goPointLogs() {
    wx.navigateTo({ url: '/pages/me/point-logs' });
  },

  go(e) {
    // pages/me/orders 是 tabBar 页面，navigateTo 无法跳转（会静默失败），需用 switchTab
    wx.switchTab({ url: e.currentTarget.dataset.url });
  },
  noop() {},
});


