const shopApi = require('../../api/shop');
const { fen2yuan } = require('../../utils/format');

const CART_KEY = 'nf_cart';

Page({
  data: {
    items: [],
    tables: [],
    tableId: 0,
    tableName: '',
    showTablePicker: false,
    payType: 2,
    remark: '',
    preview: null,
    submitting: false,
  },

  onLoad() {
    const cart = wx.getStorageSync(CART_KEY) || {};
    const items = Object.keys(cart).map((key) => ({
      goods_id: cart[key].goods_id,
      quantity: cart[key].quantity,
    }));

    if (!items.length) {
      wx.showToast({ title: '请先选择商品', icon: 'none' });
      setTimeout(() => wx.navigateBack(), 800);
      return;
    }

    this.setData({ items });
    this.loadTables();
    this.loadPreview();
  },

  loadTables() {
    shopApi.tables().then((tables) => this.setData({ tables }));
  },

  loadPreview() {
    getApp()
      .ensureLogin()
      .then(() => shopApi.preview(this.data.items))
      .then((preview) => {
        this.setData({
          preview: Object.assign({}, preview, {
            totalText: fen2yuan(preview.total_amount),
            payGiftText: fen2yuan(preview.plan.pay_gift),
            payBalanceText: fen2yuan(preview.plan.pay_balance),
            balanceText: fen2yuan(preview.balance),
            giftBalanceText: fen2yuan(preview.gift_balance),
            items: preview.items.map((item) =>
              Object.assign({}, item, { subtotalText: fen2yuan(item.subtotal) })
            ),
          }),
        });
      });
  },

  openTablePicker() {
    this.setData({ showTablePicker: true });
  },
  closeTablePicker() {
    this.setData({ showTablePicker: false });
  },
  onTableSelect(e) {
    const { id, name } = e.currentTarget.dataset;
    this.setData({ tableId: Number(id), tableName: name, showTablePicker: false });
  },
  noop() {},
  onPayTypeChange(e) {
    this.setData({ payType: Number(e.currentTarget.dataset.type) });
  },
  onRemarkInput(e) {
    this.setData({ remark: e.detail.value });
  },

  submit() {
    if (!this.data.tableId) {
      wx.showToast({ title: '请选择桌号', icon: 'none' });
      return;
    }
    if (this.data.submitting) return;

    this.setData({ submitting: true });

    shopApi
      .createOrder({
        items: JSON.stringify(this.data.items),
        table_id: this.data.tableId,
        pay_type: this.data.payType,
        remark: this.data.remark,
      })
      .then((order) => {
        if (order.pay_type === 1 && order.pay_params) {
          return this.requestPayment(order);
        }
        this.onPaid();
      })
      .catch(() => {})
      .finally(() => this.setData({ submitting: false }));
  },

  requestPayment(order) {
    return new Promise((resolve) => {
      wx.requestPayment(
        Object.assign({}, order.pay_params, {
          success: () => {
            this.onPaid();
            resolve();
          },
          fail: () => {
            wx.showToast({ title: '支付已取消，可在订单中继续支付', icon: 'none' });
            wx.removeStorageSync(CART_KEY);
            setTimeout(() => wx.redirectTo({ url: '/pages/me/orders' }), 1200);
            resolve();
          },
        })
      );
    });
  },

  onPaid() {
    wx.removeStorageSync(CART_KEY);
    wx.showToast({ title: '下单成功' });
    setTimeout(() => wx.redirectTo({ url: '/pages/me/orders' }), 1000);
  },
});
