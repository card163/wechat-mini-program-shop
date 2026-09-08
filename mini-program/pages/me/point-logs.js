const memberApi = require('../../api/member');

/** 礼品卡变动量格式化：整数张数，正数带+号 */
function amountText(raw) {
  const value = Number(raw) || 0;
  return value > 0 ? `+${value}` : `${value}`;
}

Page({
  data: {
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
    balanceText: '',
    label: '礼品卡',
  },

  onShow() {
    this.reload();
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => this.setData({ balanceText: String(member.point || 0) }))
      .catch(() => {});
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

    const params = { page: this.data.page, page_size: 20 };

    return getApp()
      .ensureLogin()
      .then(() => memberApi.pointLogs(params))
      .then((res) => {
        const list = res.list.map((log) =>
          Object.assign({}, log, {
            amount: log.point,
            amountText: amountText(log.point),
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
});
