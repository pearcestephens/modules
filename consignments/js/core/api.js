export async function post(url, data, asJson=true) {
  const body = data instanceof FormData ? data : Object.entries(data).reduce((fd,[k,v]) => (fd.append(k, v), fd), new FormData());
  const r = await fetch(url, { method:'POST', body });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return asJson ? r.json() : r.text();
}
