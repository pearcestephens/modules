export const cfg = (overrides = {}) => ({
  apiBase: '/modules/transfers/stock/api',
  csrf: '',
  transferId: 0,
  userId: 0,
  enableScanner: false,
  ...overrides
});
