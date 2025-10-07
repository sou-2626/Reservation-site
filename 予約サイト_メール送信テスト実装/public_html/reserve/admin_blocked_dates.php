<?php
// admin_blocked_dates.php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin.php');
    exit;
}

$message = '';
$blocked = [];
if (file_exists(BLOCKED_DATES_CSV)) {
    if (($fp = fopen(BLOCKED_DATES_CSV, 'r')) !== false) {
        while (($r = fgetcsv($fp)) !== false) {
            if (!empty($r[0])) $blocked[] = $r[0];
        }
        fclose($fp);
    }
}

// 追加
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_date'])) {
    $d = trim($_POST['add_date']);
    if ($d && !in_array($d, $blocked)) {
        $fp = fopen(BLOCKED_DATES_CSV, 'a');
        fputcsv($fp, [$d]);
        fclose($fp);
        $message = '追加しました';
        $blocked[] = $d;
    } else {
        $message = '入力が無いか既にあります';
    }
}

// 削除 via GET? use ?remove=YYYY-MM-DD
if (isset($_GET['remove'])) {
    $rem = $_GET['remove'];
    $new = array_values(array_filter($blocked, function($x) use ($rem){ return $x !== $rem; }));
    $fp = fopen(BLOCKED_DATES_CSV, 'w');
    foreach ($new as $n) fputcsv($fp, [$n]);
    fclose($fp);
    $message = '削除しました';
    $blocked = $new;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>予約不可日管理</title>
<style>
body{font-family:sans-serif;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:8px}
</style>
</head>
<body>
  <h1>予約不可日管理</h1>
  <?php if($message):?><p style="color:green"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><?php endif; ?>

  <h2>登録済みの予約不可日</h2>
  <table>
    <thead><tr><th>日付</th><th>操作</th></tr></thead>
    <tbody>
      <?php if (empty($blocked)): ?>
        <tr><td colspan="2">未登録</td></tr>
      <?php else: foreach ($blocked as $d): ?>
        <tr>
          <td><?=htmlspecialchars($d,ENT_QUOTES,'UTF-8')?></td>
          <td><a href="?remove=<?=urlencode($d)?>" onclick="return confirm('削除しますか？')">削除</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <h2>追加</h2>
  <form method="post" action="">
    <input type="date" name="add_date" required>
    <button type="submit">追加</button>
  </form>

  <p><a href="admin_dashboard.php">管理画面へ戻る</a></p>
</body>
</html>
