// =======================================================
// 管理画面用スクリプト：admin_dashboard.js
// =======================================================

// 🔸 メニュー内のボタンをクリック → data-go のURLへ遷移
document.querySelector('.menu')?.addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-go]');
  if (!btn) return;
  const url = btn.dataset.go;
  location.replace(url); // 戻るで管理画面に戻れないようにする
});

// 🔸 リマインド一斉送信処理
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

// 🔸 ログアウト処理
function adminLogout() {
  sessionStorage.removeItem('loggedIn');
  sessionStorage.removeItem('role');
  location.replace('admin_login.html');
}

// 🔸 イベント登録
document.getElementById('remind-btn')?.addEventListener('click', sendReminders);
document.getElementById('logout-btn')?.addEventListener('click', adminLogout);
