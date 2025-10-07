<?php
// index.php
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>予約システム</title>
  <style>
    body { font-family: sans-serif; padding: 20px; }
    h1 { text-align: center; }
    .center { text-align: center; margin-top: 30px; }
    a.button {
      display: inline-block;
      padding: 10px 20px;
      margin: 10px;
      background: #007BFF;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
    }
    a.button:hover { background: #0056b3; }
  </style>
</head>
<body>
  <h1>予約システム</h1>
  <div class="center">
    <a href="calendar.php" class="button">カレンダーから予約する</a>
    <a href="admin.php" class="button">管理者ログイン</a>
  </div>
</body>
</html>
