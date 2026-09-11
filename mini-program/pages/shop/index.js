const shopApi = require('../../api/shop');
const memberApi = require('../../api/member');
const { shopInfo } = require('../../api/auth');
const { fen2yuan, giftLabel, drinkCardLabel, giftAmountText, drinkCardAmountText } = require('../../utils/format');

const CART_KEY = 'nf_cart';

Page({
  data: {
    shop: {},
    categories: [],
    currentCategory: 0,
    goods: [],
    cart: {},
    cartList: [],
    cartCount: 0,
    cartAmountText: '0.00',
    loading: true,
    showPhoneModal: false,
    showCartModal: false,
    showDetailModal: false,
    detailLoading: false,
    detailGoods: {},
    giftLabel: '赠金',
    drinkCardLabel: '饮品卡',
  },

  onLoad() {
    this.setData({ giftLabel: giftLabel(), drinkCardLabel: drinkCardLabel() });
    shopInfo().then((shop) => this.setData({ shop }));
    this.loadCategories();
  },

  onShow() {
    this.restoreCart();
  },

  loadCategories() {
    shopApi
      .categories()
      .then((list) => {
        this.setData({ categories: list });
        return this.loadGoods(list.length ? list[0].id : 0);
      })
      .catch(() => this.setData({ loading: false }));
  },

  loadGoods(categoryId) {
    this.setData({ loading: true, currentCategory: categoryId });
    return shopApi
      .goods({ category_id: categoryId, page: 1, page_size: 100 })
      .then((res) => {
        const goods = res.list.map((item) => Object.assign({}, item, { priceText: fen2yuan(item.price) }));
        this.setData({ goods, loading: false });
        this.syncCartToGoods();
      })
      .catch(() => this.setData({ loading: false }));
  },

  onCategoryTap(e) {
    this.loadGoods(Number(e.currentTarget.dataset.id));
  },

  onAdd(e) {
    const id = Number(e.currentTarget.dataset.id);
    this.addOne(id, this.data.goods.find((item) => item.id === id));
  },

  onMinus(e) {
    this.minusOne(Number(e.currentTarget.dataset.id));
  },

  /** 加购一件，goodsRef 为商品列表中的原始记录，找不到时回退用购物车里已存的快照 */
  addOne(id, goodsRef) {
    const cart = Object.assign({}, this.data.cart);
    const existing = cart[id];
    const current = existing ? existing.quantity : 0;
    const stock = goodsRef ? goodsRef.stock : existing ? existing.stock : -1;

    if (stock !== -1 && current + 1 > stock) {
      wx.showToast({ title: '库存不足', icon: 'none' });
      return;
    }

    cart[id] = {
      goods_id: id,
      name: goodsRef ? goodsRef.name : existing.name,
      price: goodsRef ? goodsRef.price : existing.price,
      cover: goodsRef ? goodsRef.cover : existing ? existing.cover : '',
      stock,
      quantity: current + 1,
    };
    this.updateCart(cart);
  },

  minusOne(id) {
    const cart = Object.assign({}, this.data.cart);
    if (!cart[id]) return;

    cart[id].quantity -= 1;
    if (cart[id].quantity <= 0) delete cart[id];
    this.updateCart(cart);
  },

  clearCart() {
    this.updateCart({});
  },

  updateCart(cart) {
    let count = 0;
    let amount = 0;
    const cartList = [];
    Object.keys(cart).forEach((key) => {
      const item = cart[key];
      count += item.quantity;
      amount += item.price * item.quantity;
      cartList.push(
        Object.assign({}, item, {
          priceText: fen2yuan(item.price),
          subtotalText: fen2yuan(item.price * item.quantity),
        })
      );
    });

    wx.setStorageSync(CART_KEY, cart);
    this.setData({ cart, cartList, cartCount: count, cartAmountText: fen2yuan(amount) });
    this.syncCartToGoods(cart);
    if (!count) this.setData({ showCartModal: false });
  },

  restoreCart() {
    this.updateCart(wx.getStorageSync(CART_KEY) || {});
  },

  /** 把购物车数量同步到商品列表，供 wxml 直接渲染 */
  syncCartToGoods(cart) {
    const source = cart || this.data.cart;
    const goods = this.data.goods.map((item) =>
      Object.assign({}, item, { quantity: source[item.id] ? source[item.id].quantity : 0 })
    );
    this.setData({ goods });
  },

  openCartModal() {
    if (!this.data.cartCount) return;
    this.setData({ showCartModal: true });
  },

  closeCartModal() {
    this.setData({ showCartModal: false });
  },

  onClearCart() {
    this.clearCart();
  },

  noop() {},

  /** 点击商品行打开底部详情抽屉，拉取完整详情字段（包含可用赠金/饮品卡支付需消耗量、购买赠送饮品卡等） */
  openGoodsDetail(e) {
    const id = Number(e.currentTarget.dataset.id);
    this.setData({ showDetailModal: true, detailLoading: true, detailGoods: {} });
    shopApi
      .goodsDetail(id)
      .then((goods) => {
        const quantity = this.data.cart[id] ? this.data.cart[id].quantity : 0;
        const detailGoods = Object.assign({}, goods, {
          quantity,
          priceText: fen2yuan(goods.price),
          originPriceText: fen2yuan(goods.origin_price),
          stockText: goods.stock === -1 ? '不限' : String(goods.stock),
          giftAmountText: giftAmountText(goods.gift_amount),
          drinkCardAmountText: drinkCardAmountText(goods.drink_card_amount),
          drinkCardGiftAmountText: drinkCardAmountText(goods.drink_card_gift_amount),
          drinkCardGiftExpireText:
            goods.drink_card_gift_expire_days > 0 ? `有效期${goods.drink_card_gift_expire_days}天` : '永久有效',
        });
        this.setData({ detailGoods, detailLoading: false });
      })
      .catch(() => {
        this.setData({ detailLoading: false });
        wx.showToast({ title: '商品不存在或已下架', icon: 'none' });
        this.closeDetailModal();
      });
  },

  closeDetailModal() {
    this.setData({ showDetailModal: false });
  },

  onDetailAdd() {
    const id = this.data.detailGoods.id;
    this.addOne(id, this.data.detailGoods);
    this.setData({ 'detailGoods.quantity': this.data.cart[id] ? this.data.cart[id].quantity : 0 });
  },

  onDetailMinus() {
    const id = this.data.detailGoods.id;
    this.minusOne(id);
    this.setData({ 'detailGoods.quantity': this.data.cart[id] ? this.data.cart[id].quantity : 0 });
  },

  onDetailPrimary() {
    if (!this.data.detailGoods.quantity) {
      this.onDetailAdd();
    }
    this.closeDetailModal();
  },

  goCheckout() {
    if (!this.data.cartCount) {
      wx.showToast({ title: '请先选择商品', icon: 'none' });
      return;
    }
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => {
        if (!member.phone) {
          this.setData({ showPhoneModal: true });
          return;
        }
        wx.navigateTo({ url: '/pages/shop/checkout' });
      })
      .catch(() => {});
  },

  // 手机号登录成功后自动继续跳转结算，无需用户再点一次
  onPhoneBound() {
    this.setData({ showPhoneModal: false });
    wx.navigateTo({ url: '/pages/shop/checkout' });
  },
});
