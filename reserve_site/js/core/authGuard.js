// 入場ガード / ログアウト（全ページ統一）

export function guardAdmin(loginPath = 'admin_login.html') {
  try {
    const role = sessionStorage.getItem('role');
    const logged = sessionStorage.getItem('loggedIn');
    if (role !== 'admin' || logged !== 'true') location.replace(loginPath);
  } catch {
    location.replace(loginPath);
  }
}

export function guardUser(loginPath = 'login.html') {
  try {
    const logged = sessionStorage.getItem('loggedIn');
    if (logged !== 'true') location.replace(loginPath);
  } catch {
    location.replace(loginPath);
  }
}

export function adminLogout(loginPath = 'admin_login.html') {
  sessionStorage.removeItem('loggedIn');
  sessionStorage.removeItem('role');
  sessionStorage.removeItem('isAdminLoggedIn'); // 旧キー掃除
  location.replace(loginPath);
}

export function userLogout(loginPath = 'login.html') {
  sessionStorage.removeItem('loggedIn');
  sessionStorage.removeItem('role');
  location.replace(loginPath);
}
