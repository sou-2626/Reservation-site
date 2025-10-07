<?php
// admin.php : 管理者ログイン
session_start();
require_once __DIR__ . '/config.php';

// すでにログイン済みならダッシュボードへ
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id'] ?? '');
    $pass = $_POST['pass'] ?? '';

    if ($id === ADMIN_ID && $pass === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'IDまたはパスワードが違います';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>管理者ログイン</title>
<style>
body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#0f172a}
.box{background:#fff;padding:24px;border-radius:10px;width:360px}
input{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:6px;box-sizing:border-box}
button{width:100%;padding:10px;border-radius:6px;border:0;background:#111827;color:#fff;cursor:pointer}
.error{color:#e11d48;margin-top:8px}
.link{margin-top:10px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h2>管理者ログイン</h2>
  <form method="post" action="">
    <input name="id" placeholder="管理者ID" required autofocus>
    <input name="pass" type="password" placeholder="パスワード" required>
    <button type="submit">ログイン</button>
  </form>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <div class="link"><a href="index.php">ユーザー画面に戻る</a></div>
</div>
</body>
</html>
