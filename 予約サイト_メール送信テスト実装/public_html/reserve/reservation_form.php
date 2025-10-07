<?php
require_once __DIR__ . '/config.php';

$date = isset($_GET['date']) ? $_GET['date'] : '';
if (!$date) {
    header("Location: calendar.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>予約フォーム</title>
  <style>
    body { font-family: sans-serif; padding: 20px; }
    form { max-width: 500px; margin: auto; }
    label { display: block; margin: 10px 0 5px; }
    input, select { width: 100%; padding: 8px; }
    input[type="checkbox"] { width: auto; }
    button { margin-top: 20px; padding: 10px 20px; }
  </style>
</head>
<body>
  <h1 style="text-align:center;">予約フォーム</h1>
  <form action="api.php?action=create" method="post">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

    <p>予約日: <?= htmlspecialchars($date) ?></p>

    <label>企業名（必須）</label>
    <input type="text" name="company" required>

    <label>メールアドレス（必須）</label>
    <input type="email" name="email" required>

    <label>時間帯（必須）</label>
    <select name="time" required>
      <option value="午前10時～12時">午前10時～12時</option>
      <option value="午後14時～17時">午後14時～17時</option>
    </select>

    <label>カテゴリ（必須）</label>
    <select name="category" required>
      <option value="ゲーム">ゲーム</option>
      <option value="CG">CG</option>
      <option value="両方">両方</option>
    </select>

    <label>
      <input type="checkbox" name="anonymous" value="1">
      匿名で表示する
    </label>

    <button type="submit">予約する</button>
  </form>
</body>
</html>
