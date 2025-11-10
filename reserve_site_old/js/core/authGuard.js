// 入場ガード / ログアウト（全ページ統一）

// js/core/authGuard.js
export function guardUser(loginPage = "login.html") {
  const role = sessionStorage.getItem("role");
  const logged = sessionStorage.getItem("loggedIn");
  // user または admin どちらも許可
  if (logged !== "true" || (role !== "user" && role !== "admin")) {
    window.location.href = loginPage;
  }
}

// 管理者専用ページで使用（例: admin_dashboard.html）
export function guardAdmin(loginPage = "admin_login.html") {
  const role = sessionStorage.getItem("role");
  const logged = sessionStorage.getItem("loggedIn");
  if (logged !== "true" || role !== "admin") {
    window.location.href = loginPage;
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
