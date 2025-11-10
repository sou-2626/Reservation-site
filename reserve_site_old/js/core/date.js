export const pad2 = (n) => String(n).padStart(2, '0');

export function jpDateToISO(jp) {
  const m = (jp || '').match(/^(\d{4})年(\d{1,2})月(\d{1,2})日$/);
  if (!m) return '';
  const [, y, mo, d] = m;
  return `${y}-${pad2(+mo)}-${pad2(+d)}`;
}

export function isoToJP(iso) {
  const m = (iso || '').match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!m) return iso || '';
  const [, y, mo, d] = m;
  return `${y}年${+mo}月${+d}日`;
}

export function ymdISO(year, month0, day) {
  return `${year}-${pad2(month0 + 1)}-${pad2(day)}`;
}

export function normalizeISO(s) {
  if (typeof s !== 'string') return '';
  const t = s.trim();
  let m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (m) return `${m[1]}-${pad2(+m[2])}-${pad2(+m[3])}`;
  m = t.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
  if (m) return `${m[1]}-${pad2(+m[2])}-${pad2(+m[3])}`;
  m = t.match(/^(\d{4})(\d{2})(\d{2})$/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  return '';
}
