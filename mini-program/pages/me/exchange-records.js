const memberApi = require('../../api/member');
const { fen2yuan } = require('../../utils/format');

Page({
  data: {
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
    codeRecord: null,
  },

  onShow() {
    this.setData({ list: [], page: 1, hasMore: true });
    getApp()
      .ensureLogin()
      .then(() => this.loadMore());
  },

  onReachBottom() {
    this.loadMore();
  },

  loadMore() {
    if (!this.data.hasMore || this.data.loading) return Promise.resolve();
    this.setData({ loading: true });

    return memberApi
      .exchangeRecords({ page: this.data.page, page_size: 20 })
      .then((res) => {
        const list = res.list.map((item) =>
          Object.assign({}, item, { giftText: fen2yuan(item.gift_amount) })
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

  showCode(e) {
    const id = Number(e.currentTarget.dataset.id);
    memberApi.exchangeCode(id).then((record) => this.setData({ codeRecord: record }));
  },

  closeCode() {
    this.setData({ codeRecord: null });
  },

  noop() {},
});
