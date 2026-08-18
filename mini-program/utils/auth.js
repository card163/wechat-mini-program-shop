const TOKEN_KEY = 'nf_token';
const EXPIRE_KEY = 'nf_token_expires_at';

function getToken() {
  const token = wx.getStorageSync(TOKEN_KEY);
  const expiresAt = wx.getStorageSync(EXPIRE_KEY);
  if (!token) return '';
  if (expiresAt && Date.now() / 1000 > Number(expiresAt)) {
    clearToken();
    return '';
  }
  return token;
}

function setToken(token, expiresAt) {
  wx.setStorageSync(TOKEN_KEY, token);
  wx.setStorageSync(EXPIRE_KEY, expiresAt || 0);
}

function clearToken() {
  wx.removeStorageSync(TOKEN_KEY);
  wx.removeStorageSync(EXPIRE_KEY);
}

module.exports = { getToken, setToken, clearToken };
