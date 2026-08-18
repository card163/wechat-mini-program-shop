const { ranking } = require('../../api/auth');

Page({
  data: {
    list: [],
    me: null,
    loading: true,
  },

  onShow() {
    getApp()
      .ensureLogin()
      .catch(() => null)
      .then(() => ranking(50))
      .then((data) => this.setData({ list: data.list, me: data.me, loading: false }))
      .catch(() => this.setData({ loading: false }));
  },
});
