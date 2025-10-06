// js/pages/admin_dashboard.js
// 入場ガード
if (sessionStorage.getItem('role') !== 'admin' || sessionStorage.getItem('loggedIn') !== 'true') {
  location.replace('admin_login.html');
}

// ログアウト処理
function adminLogout() {
  sessionStorage.removeItem('loggedIn');
  sessionStorage.removeItem('role');
  location.replace('admin_login.html');
}

// ✅ HTML から呼べるように登録
window.adminLogout = adminLogout;
