const memberApi = require('../../api/member');
const { fen2yuan, money } = require('../../utils/format');

Page({
  data: {
    packages: [],
    member: null,
    selectedId: 0,
    submitting: false,
  },

  onShow() {
    getApp()
      .ensureLogin()
      .then(() => Promise.all([memberApi.rechargePackages(), memberApi.info()]))
      .then(([packages, member]) => {
        this.setData({
          packages: packages.map((item) =>
            Object.assign({}, item, {
              amountText: money(item.amount),
              giftText: money(item.gift_amount),
            })
          ),
          member,
          balanceText: fen2yuan(member.balance),
          giftBalanceText: fen2yuan(member.gift_balance),
          selectedId: this.data.selectedId || (packages[0] ? packages[0].id : 0),
        });
      })
      .catch(() => {});
  },

  onSelect(e) {
    this.setData({ selectedId: Number(e.currentTarget.dataset.id) });
  },

  submit() {
    if (!this.data.selectedId) {
      wx.showToast({ title: '请选择充值套餐', icon: 'none' });
      return;
    }
    if (this.data.submitting) return;

    this.setData({ submitting: true });

    memberApi
      .createRecharge(this.data.selectedId)
      .then((order) =>
        new Promise((resolve) => {
          wx.requestPayment(
            Object.assign({}, order.pay_params, {
              success: () => {
                wx.showToast({ title: '充值成功' });
                setTimeout(() => this.onShow(), 800);
                resolve();
              },
              fail: () => {
                wx.showToast({ title: '支付已取消', icon: 'none' });
                resolve();
              },
            })
          );
        })
      )
      .catch(() => {})
      .finally(() => this.setData({ submitting: false }));
  },
});
