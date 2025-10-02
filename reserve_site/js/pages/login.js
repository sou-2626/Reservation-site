// js/pages/login.js
import { getIds, login } from '../core/api.js';

// 画面メッセージ
function showError(msg) {
  const el = document.getElementById('error-message');
  if (el) el.textContent = msg || '';
}

// ボタンの状態
function setBusy(busy) {
  const btn = document.getElementById('login-btn');
  if (!btn) return;
  btn.disabled = !!busy;
  btn.textContent = busy ? '確認中…' : 'ログイン';
}

async function doUserLogin() {
  showError('');

  const inputId = (document.getElementById('username')?.value || '').trim();
  const pass    = document.getElementById('password')?.value || '';

  if (!inputId || !pass) {
    showError('ユーザーIDとパスワードを入力してください');
    return;
  }

  setBusy(true);
  try {
    // 1) サーバ登録の user.id を取得してID一致チェック
    const ids = await getIds(); // { admin:{id}, user:{id} }
    const userId = ids?.user?.id || 'user';
    if (inputId !== userId) {
      showError('ユーザーIDが見つかりません');
      return;
    }

    // 2) role=user でパスワード照合
    const res = await login('user', pass); // { ok:true } 期待
    if (!(res && res.ok === true)) {
      showError(res?.error || 'ユーザーIDまたはパスワードが違います');
      return;
    }

    // 3) セッションにログイン情報を保存 → カレンダーへ
    sessionStorage.setItem('loggedIn', 'true');
    sessionStorage.setItem('role', 'user');

    location.href = 'index.html';
  } catch (e) {
    console.error(e);
    showError('通信エラーが発生しました');
  } finally {
    setBusy(false);
  }
}

// 初期化
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    doUserLogin();
  });
});
