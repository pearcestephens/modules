export function isPositiveInt(val) {
  const n = Number(val);
  return Number.isInteger(n) && n >= 0;
}

export function clampInt(val, min, max) {
  let n = parseInt(val, 10);
  if (isNaN(n)) n = min;
  return Math.max(min, Math.min(max, n));
}
