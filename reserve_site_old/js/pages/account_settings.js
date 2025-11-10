// js/pages/account_settings.js

// 入場ガード（管理者のみ）
if (sessionStorage.getItem("role") !== "admin" || sessionStorage.getItem("loggedIn") !== "true") {
  window.location.href = "admin_login.html";
}

// 現在のIDをサーバーから取得して入力欄に反映
async function loadCurrentIds() {
  try {
    const res = await fetch('./auth.php?action=get_ids&ts=' + Date.now(), {
      headers: { 'Cache-Control': 'no-store' }, cache: 'no-store'
    });
    const json = await res.json();
    if (json?.user?.id) document.getElementById('user-id').value = json.user.id;
    if (json?.admin?.id) document.getElementById('admin-id').value = json.admin.id;
  } catch (e) {
    console.error(e);
    alert('現在のIDを取得できませんでした');
  }
}

// role: 'user' | 'admin'
async function updateAccount(role) {
  const idInput = document.getElementById(role + '-id').value.trim();
  const passInput = document.getElementById(role + '-pass').value;

  const payload = { role };
  if (idInput) payload.id = idInput;
  if (passInput) payload.password = passInput;

  try {
    const res = await fetch('./auth.php?action=update_account&ts=' + Date.now(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
      cache: 'no-store',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json && json.ok === false) {
      alert(json.error || '更新に失敗しました');
      return;
    }
    alert((role === 'admin' ? '管理者' : 'ユーザー') + '情報を更新しました');
    document.getElementById(role + '-pass').value = '';
  } catch (e) {
    console.error(e);
    alert('通信エラー');
  }
}

function goBack() {
  window.location.href = "admin_dashboard.html";
}

// DOM 初期化
document.addEventListener("DOMContentLoaded", loadCurrentIds);

// グローバル公開（HTML の onclick から呼べるようにする）
window.updateAccount = updateAccount;
window.goBack = goBack;
