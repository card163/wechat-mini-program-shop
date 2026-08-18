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

module.exports = { fen2yuan, money, formatDate };
