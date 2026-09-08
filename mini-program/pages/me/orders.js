const shopApi = require('../../api/shop');
const { fen2yuan, giftLabel, drinkCardLabel } = require('../../utils/format');

Page({
  data: {
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
  },

  onShow() {
    this.reload();
  },

  onReachBottom() {
    this.loadMore();
  },

  reload() {
    this.setData({ list: [], page: 1, hasMore: true });
    return this.loadMore();
  },

  loadMore() {
    if (!this.data.hasMore || this.data.loading) return Promise.resolve();
    this.setData({ loading: true });

    const params = { page: this.data.page, page_size: 10 };

    return getApp()
      .ensureLogin()
      .then(() => shopApi.orders(params))
      .then((res) => {
        const list = res.list.map((order) =>
          Object.assign({}, order, {
            payAmountText: fen2yuan(order.pay_amount),
            items: order.items.map((item) =>
              Object.assign({}, item, { subtotalText: fen2yuan(item.subtotal) })
            ),
          })
        );

        this.setData({
          list: this.data.list.concat(list),
          page: this.data.page + 1,
          hasMore: this.data.list.length + list.length < res.total,
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },

  onPay(e) {
    const id = Number(e.currentTarget.dataset.id);
    const payTypes = [1, 2, 3, 4];
    wx.showActionSheet({
      itemList: ['微信支付', '余额支付', `${giftLabel()}支付`, `${drinkCardLabel()}支付`],
      success: ({ tapIndex }) => {
        const payType = payTypes[tapIndex];
        shopApi.payOrder(id, payType).then((order) => {
          if (order.pay_type === 1 && order.pay_params) {
            wx.requestPayment(
              Object.assign({}, order.pay_params, {
                success: () => {
                  wx.showToast({ title: '支付成功' });
                  this.reload();
                },
                fail: () => wx.showToast({ title: '支付已取消', icon: 'none' }),
              })
            );
            return;
          }
          wx.showToast({ title: '支付成功' });
          this.reload();
        });
      },
    });
  },

  onCancel(e) {
    const id = Number(e.currentTarget.dataset.id);
    wx.showModal({
      title: '取消订单',
      content: '确定取消这笔订单吗？',
      success: ({ confirm }) => {
        if (!confirm) return;
        shopApi.cancelOrder(id).then(() => {
          wx.showToast({ title: '已取消' });
          this.reload();
        });
      },
    });
  },
});
