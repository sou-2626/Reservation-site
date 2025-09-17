function login() {
  const username = document.getElementById("username").value;
  const password = document.getElementById("password").value;

  const db = JSON.parse(localStorage.getItem("calendarData"));
  const user = db?.accounts?.user;

  if (user && username === user.id && password === user.pass) {
    sessionStorage.setItem("loggedIn", true);
    window.location.href = "index.html";
  } else {
    document.getElementById("error-message").textContent = "ログイン失敗しました";
  }
}