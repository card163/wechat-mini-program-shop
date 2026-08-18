const memberApi = require('../../api/member');
const { fen2yuan } = require('../../utils/format');

Page({
  data: {
    point: 0,
    rate: 300,
    list: [],
    loading: true,
  },

  onShow() {
    getApp()
      .ensureLogin()
      .then(() => memberApi.exchangeGoods())
      .then((data) => {
        this.setData({
          point: data.point,
          rate: data.rate,
          list: data.list.map((item) =>
            Object.assign({}, item, {
              giftText: fen2yuan(item.gift_amount),
              enough: data.point >= item.point,
              soldOut: item.stock === 0,
            })
          ),
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },

  onExchange(e) {
    const id = Number(e.currentTarget.dataset.id);
    const goods = this.data.list.find((item) => item.id === id);

    wx.showModal({
      title: '确认兑换',
      content: `消耗 ${goods.point} 记分牌兑换「${goods.name}」`,
      success: ({ confirm }) => {
        if (!confirm) return;
        memberApi.exchange(id).then((res) => {
          wx.showToast({ title: res.type === 2 ? '兑换成功' : '兑换成功，请到吧台核销' , icon: 'none' });
          this.onShow();
        });
      },
    });
  },

  goRecords() {
    wx.navigateTo({ url: '/pages/me/exchange-records' });
  },
});
