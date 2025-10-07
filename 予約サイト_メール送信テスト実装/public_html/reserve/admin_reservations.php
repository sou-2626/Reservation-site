<?php
// admin_reservations.php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin.php');
    exit;
}

$rows = [];
if (file_exists(RESERVATIONS_CSV)) {
    if (($fp = fopen(RESERVATIONS_CSV, 'r')) !== false) {
        while (($r = fgetcsv($fp)) !== false) {
            // CSV 格納順: id, date, company, email, time, category, anonymous, created_at
            $rows[] = $r;
        }
        fclose($fp);
    }
}

// 削除処理（フォームからPOSTで来る）
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = $_POST['delete_id'];
    $new = [];
    $deletedDate = null;
    foreach ($rows as $r) {
        if ($r[0] === $delId) {
            $deletedDate = $r[1];
            continue;
        }
        $new[] = $r;
    }
    // 書き戻し
    $fp = fopen(RESERVATIONS_CSV, 'w');
    foreach ($new as $nr) fputcsv($fp, $nr);
    fclose($fp);
    // blocked 更新: 削除した日に他の予約がなければ blocked から除去
    if ($deletedDate !== null) {
        $dates = array_column($new, 1);
        // rebuild blocked: keep only dates that are still reserved or admin-intended (we cannot distinguish—so we remove date only if not reserved)
        $blocked = [];
        if (file_exists(BLOCKED_DATES_CSV)) {
            if (($fp = fopen(BLOCKED_DATES_CSV, 'r')) !== false) {
                while (($b = fgetcsv($fp)) !== false) {
                    $d = $b[0];
                    // keep unless it's the deleted date and no reservation remains for it
                    if ($d === $deletedDate && !in_array($d, $dates)) {
                        // skip (remove)
                        continue;
                    }
                    $blocked[] = [$d];
                }
                fclose($fp);
            }
        }
        // write back blocked
        $fp = fopen(BLOCKED_DATES_CSV, 'w');
        foreach ($blocked as $bb) fputcsv($fp, $bb);
        fclose($fp);
    }
    $message = '削除しました';
    // reload rows for display
    $rows = $new;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>予約一覧（管理者）</title>
<style>
body{font-family:sans-serif;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:8px;text-align:left}
th{background:#f6f6f6}
.form-inline{display:inline}
.notice{color:green;margin:8px 0}
</style>
</head>
<body>
  <h1>予約一覧</h1>
  <?php if($message): ?><div class="notice"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>日付</th><th>時間</th><th>企業名</th><th>メール</th><th>匿名</th><th>カテゴリ</th><th>作成日時</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9">予約はありません</td></tr>
      <?php else: foreach ($rows as $r): 
        // id, date, company, email, time, category, anonymous, created_at
        $id = htmlspecialchars($r[0],ENT_QUOTES,'UTF-8');
        $date = htmlspecialchars($r[1],ENT_QUOTES,'UTF-8');
        $company = htmlspecialchars($r[2],ENT_QUOTES,'UTF-8');
        $email = htmlspecialchars($r[3],ENT_QUOTES,'UTF-8');
        $time = htmlspecialchars($r[4],ENT_QUOTES,'UTF-8');
        $category = htmlspecialchars($r[5],ENT_QUOTES,'UTF-8');
        $anonymous = ($r[6] == '1') ? 'はい' : 'いいえ';
        $created = htmlspecialchars($r[7] ?? '',ENT_QUOTES,'UTF-8');
      ?>
        <tr>
          <td><?= $id ?></td>
          <td><?= $date ?></td>
          <td><?= $time ?></td>
          <td><?= $company ?></td>
          <td><?= $email ?></td>
          <td><?= $anonymous ?></td>
          <td><?= $category ?></td>
          <td><?= $created ?></td>
          <td>
            <form method="post" class="form-inline" onsubmit="return confirm('削除しますか？');">
              <input type="hidden" name="delete_id" value="<?= $id ?>">
              <button type="submit">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <p><a href="admin_dashboard.php">管理画面へ戻る</a></p>
</body>
</html>
