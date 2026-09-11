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
    sections: [],
    hasGoods: false,
    scrollIntoViewId: '',
    sideScrollIntoViewId: '',
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

  // 各分类锚点相对滚动内容顶部的偏移量（滚动时联动左侧tab用），非响应式数据故不放 data 里
  sectionTops: [],
  ignoreScrollSpy: false,

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
        this.setData({ categories: list, currentCategory: list.length ? list[0].id : 0 });
        return this.loadAllSections(list);
      })
      .catch(() => this.setData({ loading: false }));
  },

  /** 一次性拉取全部分类的商品，拼成单条可滚动列表（各分类内部按原有 sort 排序） */
  loadAllSections(categories) {
    this.setData({ loading: true });
    return Promise.all(categories.map((cat) => shopApi.goods({ category_id: cat.id, page: 1, page_size: 100 })))
      .then((results) => {
        const sections = categories.map((cat, idx) => ({
          id: cat.id,
          name: cat.name,
          goods: (results[idx].list || []).map((item) => Object.assign({}, item, { priceText: fen2yuan(item.price) })),
        }));
        const hasGoods = sections.some((section) => section.goods.length > 0);
        this.setData({ sections, hasGoods, loading: false }, () => {
          this.syncCartToGoods();
          wx.nextTick(() => this.measureSections());
        });
      })
      .catch(() => this.setData({ loading: false }));
  },

  /** 测量每个分类锚点相对滚动内容顶部的偏移量，供滚动时判断当前应高亮的分类 */
  measureSections() {
    if (!this.data.sections.length) return;
    const query = wx.createSelectorQuery().in(this);
    query.select('.list').boundingClientRect();
    query.selectAll('.section-anchor').boundingClientRect();
    query.exec((res) => {
      const listRect = res[0];
      const anchors = res[1];
      if (!listRect || !anchors || !anchors.length) return;
      this.sectionTops = anchors.map((rect, idx) => ({
        id: this.data.sections[idx].id,
        top: rect.top - listRect.top,
      }));
    });
  },

  /** 点击左侧分类：滚动右侧商品列表到对应锚点，滚动动画期间暂停自动分类联动，避免和滚动监听打架 */
  onCategoryTap(e) {
    const id = Number(e.currentTarget.dataset.id);
    if (id === this.data.currentCategory) return;
    this.ignoreScrollSpy = true;
    this.setData({ currentCategory: id, scrollIntoViewId: 'anchor-' + id });
    clearTimeout(this.scrollSpyTimer);
    this.scrollSpyTimer = setTimeout(() => {
      this.ignoreScrollSpy = false;
    }, 600);
  },

  /** 右侧商品列表滚动时，根据滚动位置自动切换左侧高亮分类（节流，避免频繁 setData 造成卡顿） */
  onListScroll(e) {
    if (this.ignoreScrollSpy || !this.sectionTops.length) return;
    const now = Date.now();
    if (now - (this.lastSpyTime || 0) < 100) return;
    this.lastSpyTime = now;

    const scrollTop = e.detail.scrollTop;
    const tops = this.sectionTops;
    let active = tops[0].id;
    for (let i = 0; i < tops.length; i++) {
      if (scrollTop + 40 >= tops[i].top) {
        active = tops[i].id;
      } else {
        break;
      }
    }
    if (active !== this.data.currentCategory) {
      this.setData({ currentCategory: active, sideScrollIntoViewId: 'side-' + active });
    }
  },

  onAdd(e) {
    const id = Number(e.currentTarget.dataset.id);
    this.addOne(id, this.findGoodsRef(id));
  },

  onMinus(e) {
    this.minusOne(Number(e.currentTarget.dataset.id));
  },

  /** 在全部分类的商品里查找原始记录（用于取名称/价格/库存等快照信息） */
  findGoodsRef(id) {
    for (const section of this.data.sections) {
      const found = section.goods.find((item) => item.id === id);
      if (found) return found;
    }
    return null;
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
    const sections = this.data.sections.map((section) =>
      Object.assign({}, section, {
        goods: section.goods.map((item) =>
          Object.assign({}, item, { quantity: source[item.id] ? source[item.id].quantity : 0 })
        ),
      })
    );
    this.setData({ sections });
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
