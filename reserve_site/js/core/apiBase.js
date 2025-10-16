// js/core/apiBase.js（Lollipop固定パス版）

let API_BASE = "";
let AUTH_BASE = "";

// ---- api.php を /reserve_site に固定して確認 ----
export async function resolveApiBase() {
  if (API_BASE) return;
  const fixed = "/reserve_site";
  try {
    const r = await fetch(`${fixed}/api.php?action=ping&ts=${Date.now()}`, { cache: "no-store" });
    if (r.ok) { API_BASE = fixed; return; }
    throw new Error(`API ping ${r.status}`);
  } catch (e) {
    throw new Error("api.php が見つかりません（/reserve_site/api.php を確認）");
  }
}

// ---- auth.php も /reserve_site に固定 ----
export async function resolveAuthBase() {
  if (AUTH_BASE) return;
  const fixed = "/reserve_site";
  try {
    const r = await fetch(`${fixed}/auth.php?action=ping&ts=${Date.now()}`, { cache: "no-store" });
    if (r.ok) { AUTH_BASE = fixed; return; }
    throw new Error(`AUTH ping ${r.status}`);
  } catch (e) {
    throw new Error("auth.php が見つかりません（/reserve_site/auth.php を確認）");
  }
}

// ---- URL組み立て（名前付きexport）----
export function apiURL(query) {
  return `${API_BASE}/api.php?${query}&ts=${Date.now()}`;
}
export function authURL(query) {
  return `${AUTH_BASE}/auth.php?${query}&ts=${Date.now()}`;
}
