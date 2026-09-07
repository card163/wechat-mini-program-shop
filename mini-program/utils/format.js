/** 分转元，保留两位小数 */
function fen2yuan(fen) {
  return ((Number(fen) || 0) / 100).toFixed(2);
}

/** 分转元，整数不显示小数（用于金额较大的展示位） */
function money(fen) {
  const yuan = (Number(fen) || 0) / 100;
  return Number.isInteger(yuan) ? String(yuan) : yuan.toFixed(2);
}

function formatDate(datetime) {
  return (datetime || '').replace('T', ' ').slice(0, 16);
}

/** 赠金展示配置：{ displayName, unit }，后台可配置（如改名"酒水卡"、单位"元/张"） */
function giftConfig() {
  const app = getApp();
  return (app && app.globalData && app.globalData.giftConfig) || { displayName: '赠金', unit: '元' };
}

/** 赠金对外展示名称，默认"赠金" */
function giftLabel() {
  return giftConfig().displayName;
}

/** 按配置单位格式化赠金数值，含单位符号：单位"元"输出如 ¥12.00，单位"张"输出如 12张 */
function giftAmountText(raw) {
  const value = Number(raw) || 0;
  const cfg = giftConfig();
  return cfg.unit === '张' ? `${value}张` : `¥${fen2yuan(value)}`;
}

/** 饮品卡展示配置：{ displayName, unit }，后台可配置 */
function drinkCardConfig() {
  const app = getApp();
  return (app && app.globalData && app.globalData.drinkCardConfig) || { displayName: '饮品卡', unit: '元' };
}

/** 饮品卡对外展示名称，默认"饮品卡" */
function drinkCardLabel() {
  return drinkCardConfig().displayName;
}

/** 按配置单位格式化饮品卡数值，含单位符号：单位"元"输出如 ¥12.00，单位"张"输出如 12张 */
function drinkCardAmountText(raw) {
  const value = Number(raw) || 0;
  const cfg = drinkCardConfig();
  return cfg.unit === '张' ? `${value}张` : `¥${fen2yuan(value)}`;
}

module.exports = { fen2yuan, money, formatDate, giftLabel, giftAmountText, drinkCardLabel, drinkCardAmountText };
