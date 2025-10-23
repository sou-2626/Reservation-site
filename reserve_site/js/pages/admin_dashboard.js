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

// ✅ 一斉リマインド送信処理
async function sendReminders() {
  const ok = confirm("今日以降10日以内の予約にリマインドメールを一斉送信します。\n実行してよろしいですか？");
  if (!ok) return;

  const btn = document.getElementById('remind-btn');
  btn.disabled = true;
  btn.textContent = "送信中...";

  try {
    const res = await fetch('send_reminder.php');
    const text = await res.text();
    alert(text);
  } catch (e) {
    alert("送信中にエラーが発生しました。\n" + e);
  } finally {
    btn.disabled = false;
    btn.textContent = "リマインド一斉送信";
  }
}

// HTMLから呼べるように登録
window.adminLogout = adminLogout;
document.getElementById('remind-btn').addEventListener('click', sendReminders);
