// js/pages/adminLogin.js
import { getIds, login } from '../core/api.js';

// ---- UI helpers ----
function showError(msg) {
  const el = document.getElementById('error-message');
  if (el) el.textContent = msg || '';
}
function setBusy(busy) {
  const btn = document.getElementById('admin-login-btn');
  if (!btn) return;
  btn.disabled = !!busy;
  btn.textContent = busy ? '確認中…' : 'ログイン';
}

// すでに管理者ログイン済みならダッシュボードへ
(function redirectIfAlreadyLoggedIn() {
  try {
    const role = sessionStorage.getItem('role');
    const logged = sessionStorage.getItem('loggedIn');
    if (role === 'admin' && logged === 'true') {
      location.replace('admin_dashboard.html');
    }
  } catch {}
})();

// ---- ログイン処理 ----
async function doAdminLogin() {
  showError('');

  const inputId = (document.getElementById('admin-id')?.value || '').trim();
  const pass    = document.getElementById('admin-pass')?.value || '';

  if (!inputId || !pass) {
    showError('管理者IDとパスワードを入力してください');
    return;
  }

  setBusy(true);
  try {
    // 1) サーバ登録の admin.id を取得してID一致チェック
    const ids = await getIds(); // { admin:{id}, user:{id} }
    const adminId = ids?.admin?.id || 'admin';
    if (inputId !== adminId) {
      showError('管理者IDが見つかりません');
      return;
    }

    // 2) role=admin でパスワード照合
    const res = await login('admin', pass); // { ok:true } 期待
    if (!(res && res.ok === true)) {
      showError(res?.error || '管理者IDまたはパスワードが違います');
      return;
    }

    // 3) セッション保存 → ダッシュボードへ
    sessionStorage.setItem('loggedIn', 'true');
    sessionStorage.setItem('role', 'admin');

    location.replace('admin_dashboard.html');
  } catch (e) {
    console.error(e);
    showError('通信エラーが発生しました');
  } finally {
    setBusy(false);
  }
}

// ---- 初期化 ----
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('admin-login-form');
  const back = document.getElementById('back-btn');

  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      doAdminLogin();
    });
  }
  if (back) {
    back.addEventListener('click', () => {
      location.href = 'index.html';
    });
  }
});
