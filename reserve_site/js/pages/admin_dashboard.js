// 入場ガード
if (sessionStorage.getItem('role') !== 'admin' || sessionStorage.getItem('loggedIn') !== 'true') {
  location.replace('admin_login.html');
}

// ログアウト処理
function adminLogout() {
  sessionStorage.clear();
  location.replace('login.html');
}