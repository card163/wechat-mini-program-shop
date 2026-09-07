const shopApi = require('../../api/shop');
const { fen2yuan, giftLabel, giftAmountText, drinkCardLabel, drinkCardAmountText } = require('../../utils/format');

const CART_KEY = 'nf_cart';

Page({
  data: {
    items: [],
    zones: [],
    tableId: 0,
    tableName: '',
    tempTableId: 0,
    tempTableName: '',
    showTablePicker: false,
    payType: 1,
    remark: '',
    preview: null,
    submitting: false,
    giftLabel: '赠金',
    drinkCardLabel: '饮品卡',
  },

  onLoad() {
    this.setData({ giftLabel: giftLabel(), drinkCardLabel: drinkCardLabel() });
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
    shopApi.tables().then((zones) => this.setData({ zones }));
  },

  loadPreview() {
    getApp()
      .ensureLogin()
      .then(() => shopApi.preview(this.data.items))
      .then((preview) => {
        this.setData({
          preview: Object.assign({}, preview, {
            totalText: fen2yuan(preview.total_amount),
            payGiftText: giftAmountText(preview.plan.pay_gift),
            payDrinkCardText: drinkCardAmountText(preview.plan.pay_drink_card),
            payBalanceText: fen2yuan(preview.plan.pay_balance),
            balanceText: fen2yuan(preview.balance),
            giftBalanceText: giftAmountText(preview.gift_balance),
            drinkCardBalanceText: drinkCardAmountText(preview.drink_card_balance),
            items: preview.items.map((item) =>
              Object.assign({}, item, { priceText: fen2yuan(item.price), subtotalText: fen2yuan(item.subtotal) })
            ),
          }),
        });
      });
  },

  openTablePicker() {
    this.setData({
      tempTableId: this.data.tableId,
      tempTableName: this.data.tableName,
      showTablePicker: true,
    });
  },
  closeTablePicker() {
    this.setData({ showTablePicker: false });
  },
  onTableSelect(e) {
    const { zoneName, id, name } = e.currentTarget.dataset;
    const tableName = zoneName ? `${zoneName} ${name}` : name;
    this.setData({ tempTableId: Number(id), tempTableName: tableName });
  },
  confirmTablePicker() {
    if (!this.data.tempTableId) {
      wx.showToast({ title: '请选择桌号', icon: 'none' });
      return;
    }
    this.setData({
      tableId: this.data.tempTableId,
      tableName: this.data.tempTableName,
      showTablePicker: false,
    });
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
            // pages/me/orders 是 tabBar 页面，需用 switchTab 而非 redirectTo
            setTimeout(() => wx.switchTab({ url: '/pages/me/orders' }), 1200);
            resolve();
          },
        })
      );
    });
  },

  onPaid() {
    wx.removeStorageSync(CART_KEY);
    wx.showToast({ title: '下单成功' });
    setTimeout(() => wx.switchTab({ url: '/pages/me/orders' }), 1000);
  },
});
