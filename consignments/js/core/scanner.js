export function mountKeyboardScanner({ root, onCode, timeoutMs = 200 }) {
  let buf = '';
  let timer = null;
  const handler = (e) => {
    if (e.key.length === 1) {
      buf += e.key;
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => {
        const code = buf.trim();
        buf = '';
        if (code) onCode(code);
      }, timeoutMs);
    } else if (e.key === 'Enter') {
      const code = buf.trim();
      buf = '';
      if (code) onCode(code);
    }
  };
  root.addEventListener('keydown', handler);
  return () => root.removeEventListener('keydown', handler);
}
