const memberApi = require('../../api/member');

Page({
  data: {
    point: 0,
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
  },

  onLoad() {
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => this.setData({ point: member.point }))
      .then(() => this.loadMore());
  },

  onReachBottom() {
    this.loadMore();
  },

  loadMore() {
    if (!this.data.hasMore || this.data.loading) return Promise.resolve();
    this.setData({ loading: true });

    return memberApi
      .pointLogs({ page: this.data.page, page_size: 20 })
      .then((res) => {
        this.setData({
          list: this.data.list.concat(res.list),
          page: this.data.page + 1,
          hasMore: this.data.list.length + res.list.length < res.total,
          loading: false,
        });
      })
      .catch(() => this.setData({ loading: false }));
  },
});
