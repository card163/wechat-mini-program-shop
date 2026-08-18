const memberApi = require('../../api/member');

Page({
  data: {
    storages: [],
    takes: [],
    loading: true,
    takeCode: null,
    takesVisible: false,
    storeCodeVisible: false,
    storeQrcode: '',
    storeScene: '',
  },

  onLoad(query) {
    if (query.autoCode) {
      this.goStoreCode();
    }
  },

  onShow() {
    this.loadData();
  },

  loadData() {
    return getApp()
      .ensureLogin()
      .then(() => Promise.all([
        memberApi.wineStorages({ page: 1, page_size: 50 }),
        memberApi.wineTakes({ page: 1, page_size: 50 }),
      ]))
      .then(([storages, takes]) => {
        this.setData({ storages: storages.list, takes: takes.list, loading: false });
      })
      .catch(() => this.setData({ loading: false }));
  },

  showTakes() {
    this.setData({ takesVisible: true });
  },
  hideTakes() {
    this.setData({ takesVisible: false });
  },

  goStoreCode() {
    this.setData({ storeCodeVisible: true, storeQrcode: '', storeScene: '' });
    memberApi.wineStoreCode().then((data) => {
      this.setData({
        storeQrcode: data.qrcode,
        storeScene: data.scene,
      });
    });
  },
  closeStoreCode() {
    this.setData({ storeCodeVisible: false });
  },

  onTake(e) {
    const id = Number(e.currentTarget.dataset.id);
    const storage = this.data.storages.find((item) => item.id === id);

    wx.showModal({
      title: '取酒',
      content: `确认取出 1 ${storage.unit}「${storage.wine_name}」？取酒码需店员核销`,
      success: ({ confirm }) => {
        if (!confirm) return;
        memberApi.wineTake(id, 1).then((record) => {
          this.setData({ takeCode: record });
          this.loadData();
        });
      },
    });
  },

  showTakeCode(e) {
    const id = Number(e.currentTarget.dataset.id);
    const record = this.data.takes.find((item) => item.id === id);
    this.setData({
      takeCode: {
        take_no: record.take_no,
        wine_name: record.wine_name,
        quantity: record.quantity,
      },
    });
  },

  onCancelTake(e) {
    const id = Number(e.currentTarget.dataset.id);
    wx.showModal({
      title: '取消取酒码',
      content: '取消后需要重新发起取酒',
      success: ({ confirm }) => {
        if (!confirm) return;
        memberApi.cancelWineTake(id).then(() => this.loadData());
      },
    });
  },

  closeCode() {
    this.setData({ takeCode: null });
  },

  noop() {},
});
