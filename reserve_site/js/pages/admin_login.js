import { getIds, login } from '../core/api.js';

// ---- UI helpers ----
function showError(msg) {
  const el = document.getElementById('error-message');
  if (el) el.textContent = msg || '';
}
function setBusy(busy) {
  const btn = document.getElementById('login-btn');
  if (!btn) return;
  btn.disabled = !!busy;
  btn.textContent = busy ? '確認中…' : 'ログイン';
}

// ✅ セッション初期化
document.addEventListener('DOMContentLoaded', () => {
  try {
    sessionStorage.removeItem('loggedIn');
    sessionStorage.removeItem('role');

    // 🔹「カレンダーに戻る」ボタンで user ロールとして戻る処理を追加
    const backBtn = document.getElementById('back-btn');
    if (backBtn) {
      backBtn.addEventListener('click', () => {
        sessionStorage.setItem('loggedIn', 'true');
        sessionStorage.setItem('role', 'user');
        location.href = 'index.html';
      });
    }
  } catch {}
});

// ---- ログイン処理 ----
async function doAdminLogin() {
  showError('');

  const inputId = (document.getElementById('admin-id')?.value || '').trim();
  const pass = document.getElementById('admin-pass')?.value || '';

  if (!inputId || !pass) {
    showError('管理者IDとパスワードを入力してください');
    return;
  }

  setBusy(true);
  try {
    const ids = await getIds();
    const adminId = ids?.admin?.id || 'admin';
    if (inputId !== adminId) {
      showError('管理者IDが見つかりません');
      return;
    }

    const res = await login('admin', pass);
    if (!(res && res.ok === true)) {
      showError(res?.error || '管理者IDまたはパスワードが違います');
      return;
    }

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

// ---- イベント登録 ----
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('admin-login-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      doAdminLogin();
    });
  }
});
