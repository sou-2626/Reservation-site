// js/core/api.js
import { resolveApiBase, resolveAuthBase, apiURL, authURL } from './apiBase.js';

// ===== 予約API =====

// 一覧取得
export async function listReservations() {
  await resolveApiBase();
  const r = await fetch(apiURL('action=reservation_list'), {
    cache: 'no-store',
    headers: { 'Cache-Control': 'no-store' }
  });
  if (!r.ok) throw new Error('reservation_list HTTP ' + r.status);
  return r.json();
}

// 新規作成
export async function createReservation(payload) {
  await resolveApiBase();
  const r = await fetch(apiURL('action=reservation_add'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify(payload)
  });
  if (!r.ok) throw new Error('reservation_add HTTP ' + r.status);
  return r.json();
}

// 更新
export async function updateReservation(payload) {
  await resolveApiBase();
  const r = await fetch(apiURL('action=reservation_update'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify(payload)
  });
  if (!r.ok) throw new Error('reservation_update HTTP ' + r.status);
  return r.json();
}

// 削除（修正版）
export async function deleteReservation(id) {
  await resolveApiBase();
  const r = await fetch(apiURL('action=reservation_delete'), { // ← 修正ポイント
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify({ id })
  });
  if (!r.ok) throw new Error('reservation_delete HTTP ' + r.status);
  return r.json();
}


// ===== ブロック日API =====
export async function listBlocked() {
  await resolveApiBase();
  const r = await fetch(apiURL('action=blocked_list'), {
    cache: 'no-store',
    headers: { 'Cache-Control': 'no-store' }
  });
  if (!r.ok) throw new Error('blocked_list HTTP ' + r.status);
  return r.json();
}

export async function addBlocked(date, reason = '') {
  await resolveApiBase();
  const r = await fetch(apiURL('action=blocked_add'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify({ date, reason })
  });
  if (!r.ok) throw new Error('blocked_add HTTP ' + r.status);
  return r.json();
}

export async function deleteBlocked(date) {
  await resolveApiBase();
  const r = await fetch(apiURL('action=blocked_delete'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify({ date })
  });
  if (!r.ok) throw new Error('blocked_delete HTTP ' + r.status);
  return r.json();
}

// ===== 認証API =====
export async function getIds() {
  await resolveAuthBase();
  const r = await fetch(authURL('action=get_ids'), {
    cache: 'no-store',
    headers: { 'Cache-Control': 'no-store' }
  });
  if (!r.ok) throw new Error('get_ids HTTP ' + r.status);
  return r.json();
}

export async function login(role, password) {
  await resolveAuthBase();
  const r = await fetch(authURL('action=login'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify({ role, password })
  });
  if (!r.ok) throw new Error('login HTTP ' + r.status);
  return r.json();
}

export async function updateAccount(role, payload) {
  await resolveAuthBase();
  const r = await fetch(authURL('action=update_account'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify({ role, ...payload })
  });
  if (!r.ok) throw new Error('update_account HTTP ' + r.status);
  return r.json();
}

export async function changePassword(role, new_password, old_password = undefined) {
  await resolveAuthBase();
  const body = { role, new_password };
  if (typeof old_password === 'string') body.old_password = old_password;
  const r = await fetch(authURL('action=change_password'), {
    method: 'POST',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store'
    },
    body: JSON.stringify(body)
  });
  if (!r.ok) throw new Error('change_password HTTP ' + r.status);
  return r.json();
}
