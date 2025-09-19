function adminLogin() {
  const username = document.getElementById("username").value;
  const password = document.getElementById("password").value;

  const db = JSON.parse(localStorage.getItem("calendarData"));
  const admin = db?.accounts?.admin;

  if (admin && username === admin.id && password === admin.pass) {
    sessionStorage.setItem("isAdminLoggedIn", true);
    window.location.href = "dashboard.html";
  } else {
    document.getElementById("error-message").textContent = "ログイン失敗しました";
  }
}
