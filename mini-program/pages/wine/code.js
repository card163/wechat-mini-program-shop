const memberApi = require('../../api/member');

Page({
  data: {
    scene: '',
    qrcode: '',
    countdown: 0,
    loading: true,
  },

  onLoad() {
    this.refresh();
  },

  onUnload() {
    this.clearTimer();
  },

  refresh() {
    this.clearTimer();
    this.setData({ loading: true });

    return getApp()
      .ensureLogin()
      .then(() => memberApi.wineStoreCode())
      .then((data) => {
        this.setData({ scene: data.scene, qrcode: data.qrcode, loading: false });
        this.startCountdown(data.expires_at);
      })
      .catch(() => this.setData({ loading: false }));
  },

  startCountdown(expiresAt) {
    const tick = () => {
      const left = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
      this.setData({ countdown: left });
      if (left <= 0) this.clearTimer();
    };

    tick();
    this.timer = setInterval(tick, 1000);
  },

  clearTimer() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  },
});
