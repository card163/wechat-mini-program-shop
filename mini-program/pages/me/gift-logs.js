const memberApi = require('../../api/member');
const { fen2yuan } = require('../../utils/format');

Page({
  data: {
    summary: null,
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
  },

  onLoad() {
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
      .giftBatches({ page: this.data.page, page_size: 20 })
      .then((res) => {
        const list = res.list.map((batch) =>
          Object.assign({}, batch, {
            amountText: fen2yuan(batch.amount),
            remainText: fen2yuan(batch.remain_amount),
          })
        );

        this.setData({
          summary: {
            giftBalanceText: fen2yuan(res.summary.gift_balance),
            expiringText: fen2yuan(res.summary.expiring_amount),
            expiringAt: res.summary.expiring_at,
            hasExpiring: res.summary.expiring_amount > 0,
          },
          list: this.data.list.concat(list),
          page: this.data.page + 1,
          hasMore: this.data.list.length + list.length < res.total,
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },
});
