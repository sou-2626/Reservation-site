<?php
// admin_dashboard.php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>管理者ダッシュボード</title>
<style>
body{font-family:sans-serif;padding:20px}
h1{text-align:center}
.menu{max-width:800px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
.button{display:inline-block;padding:10px 14px;background:#0f172a;color:#fff;border-radius:8px;text-decoration:none}
.logout{margin-top:18px}
</style>
</head>
<body>
  <h1>管理者ダッシュボード</h1>
  <div class="menu">
    <a class="button" href="admin_reservations.php">予約一覧</a>
    <a class="button" href="admin_blocked_dates.php">予約不可日管理</a>
    <a class="button" href="admin_emails.php">通知先管理（管理者メール）</a>
    <a class="button" href="account_settings.php">アカウント設定</a>
    <form class="logout" method="post" action="admin_logout.php" style="display:inline;">
      <button type="submit" style="padding:8px 12px;border-radius:8px;border:0;background:#e11d48;color:#fff;cursor:pointer">ログアウト</button>
    </form>
  </div>
</body>
</html>
