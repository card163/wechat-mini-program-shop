const { bindPhone } = require('../../api/auth');

// 强制手机号登录：未完成前弹窗常驻，不提供跳过入口
Component({
  data: {
    loading: false,
  },
  methods: {
    onGetPhoneNumber(e) {
      const { code, errno } = e.detail || {};
      if (errno || !code) {
        wx.showToast({ title: '需要授权手机号才能继续使用', icon: 'none' });
        return;
      }
      this.setData({ loading: true });
      bindPhone(code)
        .then(() => {
          this.triggerEvent('login');
        })
        .catch(() => {})
        .finally(() => this.setData({ loading: false }));
    },
    noop() {},
  },
});
