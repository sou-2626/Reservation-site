// js/core/apiBase.js

let API_BASE = "";
let AUTH_BASE = "";

// --- api.php の場所を ./ → ../ → ../../ の順で探す ---
export async function resolveApiBase() {
  if (API_BASE) return;
  try {
    const r = await fetch('./api.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { API_BASE = '.'; return; }
  } catch { }
  try {
    const r = await fetch('../api.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { API_BASE = '..'; return; }
  } catch { }
  try {
    const r = await fetch('../../api.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { API_BASE = '../..'; return; }
  } catch { }
  throw new Error('api.php が見つかりません（./ または ../ または ../../）');
}


// --- auth.php の場所を ./ → ../ → ../../ の順で探す ---
export async function resolveAuthBase() {
  if (AUTH_BASE) return;
  try {
    const r = await fetch('./auth.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { AUTH_BASE = '.'; return; }
  } catch { }
  try {
    const r = await fetch('../auth.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { AUTH_BASE = '..'; return; }
  } catch { }
  try {
    const r = await fetch('../../auth.php?action=ping&ts=' + Date.now(), { cache: 'no-store' });
    if (r.ok) { AUTH_BASE = '../..'; return; }
  } catch { }
  throw new Error('auth.php が見つかりません（./ または ../ または ../../）');
}


// --- URL を組み立てる（名前付き export!!） ---
export function apiURL(query) {
  return `${API_BASE}/api.php?${query}&ts=${Date.now()}`;
}

export function authURL(query) {
  return `${AUTH_BASE}/auth.php?${query}&ts=${Date.now()}`;
}
