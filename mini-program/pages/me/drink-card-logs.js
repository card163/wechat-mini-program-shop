const memberApi = require('../../api/member');
const { fen2yuan, drinkCardLabel, drinkCardAmountText } = require('../../utils/format');

function unit() {
  const app = getApp();
  const cfg = (app && app.globalData && app.globalData.drinkCardConfig) || {};
  return cfg.unit === '张' ? '张' : '元';
}

/** 流水变动量格式化：单位"元"按分转元保留符号，单位"张"直接展示整数 */
function amountText(raw) {
  const value = Number(raw) || 0;
  const sign = value > 0 ? '+' : '';
  return unit() === '张' ? `${sign}${value}张` : `${sign}${fen2yuan(value)}`;
}

Page({
  data: {
    list: [],
    page: 1,
    hasMore: true,
    loading: false,
    balanceText: '',
    label: '饮品卡',
  },

  onLoad() {
    const label = drinkCardLabel();
    this.setData({ label });
    wx.setNavigationBarTitle({ title: `${label}流水` });
  },

  onShow() {
    this.reload();
    getApp()
      .ensureLogin()
      .then(() => memberApi.info())
      .then((member) => this.setData({ balanceText: drinkCardAmountText(member.drink_card_balance) }))
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
      .then(() => memberApi.drinkCardLogs(params))
      .then((res) => {
        const list = res.list.map((log) =>
          Object.assign({}, log, {
            amountText: amountText(log.amount),
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
