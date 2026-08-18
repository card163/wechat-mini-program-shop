const memberApi = require('../../api/member');
const { updateProfile, bindPhone } = require('../../api/auth');
const { fen2yuan } = require('../../utils/format');

Page({
  data: {
    member: null,
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

  onGetPhone(e) {
    if (!e.detail.code) {
      wx.showToast({ title: '已取消', icon: 'none' });
      return;
    }
    bindPhone(e.detail.code).then(() => {
      wx.showToast({ title: '绑定成功' });
      this.onShow();
    });
  },

  go(e) {
    wx.navigateTo({ url: e.currentTarget.dataset.url });
  },
});
