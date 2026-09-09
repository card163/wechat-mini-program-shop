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
    // 微信+酒水卡/饮品卡组合支付：comboConfirmed 表示用户已在弹窗里确认启用组合抵扣
    comboModalVisible: false,
    comboConfirmed: false,
    comboUseGift: true,
    comboUseDrinkCard: true,
    comboTempUseGift: true,
    comboTempUseDrinkCard: true,
    comboSummary: null,
    comboPreview: null,
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
        if (this.removeInvalidItems(preview.removed_items)) return;

        this.setData({
          preview: Object.assign({}, preview, {
            totalText: fen2yuan(preview.total_amount),
            balanceText: fen2yuan(preview.balance),
            giftBalanceText: giftAmountText(preview.gift_balance),
            drinkCardBalanceText: drinkCardAmountText(preview.drink_card_balance),
            giftPayableText: giftAmountText(preview.gift_payable_amount),
            drinkCardPayableText: drinkCardAmountText(preview.drink_card_payable_amount),
            items: preview.items.map((item) =>
              Object.assign({}, item, { priceText: fen2yuan(item.price), subtotalText: fen2yuan(item.subtotal) })
            ),
          }),
        });

        this.ensurePayTypeUsable();
      });
  },

  /**
   * 微信支付/余额支付/{{giftLabel}}支付/{{drinkCardLabel}}支付四种方式各自独立扣款，
   * 不再互相组合；映射为预览接口 pay_options 里对应的 key
   */
  payOptionKey(payType) {
    return { 2: 'balance', 3: 'gift', 4: 'drink_card' }[payType];
  },
  isPayOptionUsable(payType) {
    if (payType === 1) return true;
    const key = this.payOptionKey(payType);
    const options = this.data.preview && this.data.preview.pay_options;
    return !!(key && options && options[key] && options[key].usable);
  },
  // 预览刷新后（如购物车被清理）当前选中的支付方式可能不再可用，自动回退到微信支付
  ensurePayTypeUsable() {
    if (!this.isPayOptionUsable(this.data.payType)) {
      this.setData({ payType: 1 });
    }
  },

  /**
   * 购物车里的商品在下单前被商家下架/删除时，预览接口会自动剔除并通过 removed_items 告知，
   * 这里同步清理本地购物车缓存，避免用户永久卡在"商品已下架"的结算页
   * @returns {boolean} 是否已处理（返回 true 时调用方应停止渲染本次预览结果）
   */
  removeInvalidItems(removedItems) {
    if (!removedItems || !removedItems.length) return false;

    const removedIds = removedItems.map((item) => item.goods_id);
    const names = removedItems.map((item) => item.goods_name).filter(Boolean).join('、');
    const remainItems = this.data.items.filter((item) => !removedIds.includes(item.goods_id));

    const cart = wx.getStorageSync(CART_KEY) || {};
    removedIds.forEach((id) => delete cart[id]);
    wx.setStorageSync(CART_KEY, cart);

    wx.showToast({ title: `${names || '部分商品'}已下架，已自动移出购物车`, icon: 'none' });

    if (!remainItems.length) {
      setTimeout(() => wx.navigateBack(), 1200);
      return true;
    }

    this.setData({ items: remainItems });
    this.loadPreview();
    return true;
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
    const payType = Number(e.currentTarget.dataset.type);
    if (!this.isPayOptionUsable(payType)) {
      wx.showToast({ title: '该支付方式暂不可用', icon: 'none' });
      return;
    }
    // 微信支付若检测到酒水卡/饮品卡余额不足以单独支付整单，弹窗询问是否合并微信组合支付
    if (payType === 1 && this.data.preview.combo_pay && this.data.preview.combo_pay.show) {
      this.openComboModal();
      return;
    }
    this.setData({ payType, comboConfirmed: false });
  },
  onRemarkInput(e) {
    this.setData({ remark: e.detail.value });
  },

  openComboModal() {
    this.setData({
      comboModalVisible: true,
      comboTempUseGift: this.data.comboUseGift,
      comboTempUseDrinkCard: this.data.comboUseDrinkCard,
    });
    this.loadComboPreview();
  },
  closeComboModal() {
    this.setData({ comboModalVisible: false });
  },
  // 弹窗内实时按当前勾选状态向服务端要「本次微信实付多少钱」，前端不自行计算金额
  loadComboPreview() {
    shopApi
      .preview(this.data.items, this.data.comboTempUseGift, this.data.comboTempUseDrinkCard)
      .then((preview) => {
        const combo = preview.combo_pay;
        this.setData({
          comboPreview: Object.assign({}, combo, {
            giftCashText: fen2yuan(combo.gift_cash),
            drinkCardCashText: fen2yuan(combo.drink_card_cash),
            wechatAmountText: fen2yuan(combo.wechat_amount),
          }),
        });
      });
  },
  onToggleComboGift() {
    this.setData({ comboTempUseGift: !this.data.comboTempUseGift }, () => this.loadComboPreview());
  },
  onToggleComboDrinkCard() {
    this.setData({ comboTempUseDrinkCard: !this.data.comboTempUseDrinkCard }, () => this.loadComboPreview());
  },
  useWechatOnly() {
    this.setData({ payType: 1, comboConfirmed: false, comboModalVisible: false });
  },
  confirmCombo() {
    if (!this.data.comboTempUseGift && !this.data.comboTempUseDrinkCard) {
      wx.showToast({ title: '请至少选择一种余额参与组合支付', icon: 'none' });
      return;
    }
    this.setData({
      payType: 1,
      comboConfirmed: true,
      comboUseGift: this.data.comboTempUseGift,
      comboUseDrinkCard: this.data.comboTempUseDrinkCard,
      comboSummary: this.data.comboPreview,
      comboModalVisible: false,
    });
  },

  submit() {
    if (!this.data.tableId) {
      wx.showToast({ title: '请选择桌号', icon: 'none' });
      return;
    }
    if (this.data.submitting) return;

    this.setData({ submitting: true });

    const useCombo = this.data.payType === 1 && this.data.comboConfirmed && (this.data.comboUseGift || this.data.comboUseDrinkCard);

    shopApi
      .createOrder({
        items: JSON.stringify(this.data.items),
        table_id: this.data.tableId,
        pay_type: useCombo ? 5 : this.data.payType,
        remark: this.data.remark,
        use_gift: useCombo && this.data.comboUseGift ? 1 : 0,
        use_drink_card: useCombo && this.data.comboUseDrinkCard ? 1 : 0,
      })
      .then((order) => {
        if ((order.pay_type === 1 || order.pay_type === 5) && order.pay_params) {
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
