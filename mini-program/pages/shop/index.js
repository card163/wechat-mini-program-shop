const shopApi = require('../../api/shop');
const { shopInfo } = require('../../api/auth');
const { fen2yuan } = require('../../utils/format');

const CART_KEY = 'nf_cart';

Page({
  data: {
    shop: {},
    categories: [],
    currentCategory: 0,
    goods: [],
    cart: {},
    cartCount: 0,
    cartAmountText: '0.00',
    loading: true,
  },

  onLoad() {
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
    const goods = this.data.goods.find((item) => item.id === id);
    const cart = Object.assign({}, this.data.cart);
    const current = cart[id] ? cart[id].quantity : 0;

    if (goods.stock !== -1 && current + 1 > goods.stock) {
      wx.showToast({ title: '库存不足', icon: 'none' });
      return;
    }

    cart[id] = { goods_id: id, name: goods.name, price: goods.price, quantity: current + 1 };
    this.updateCart(cart);
  },

  onMinus(e) {
    const id = Number(e.currentTarget.dataset.id);
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
    Object.keys(cart).forEach((key) => {
      count += cart[key].quantity;
      amount += cart[key].price * cart[key].quantity;
    });

    wx.setStorageSync(CART_KEY, cart);
    this.setData({ cart, cartCount: count, cartAmountText: fen2yuan(amount) });
    this.syncCartToGoods(cart);
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

  goCheckout() {
    if (!this.data.cartCount) {
      wx.showToast({ title: '请先选择商品', icon: 'none' });
      return;
    }
    wx.navigateTo({ url: '/pages/shop/checkout' });
  },
});
