<?php
// admin_emails.php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin.php');
    exit;
}

$emails = getAdminEmails();
$message = '';

// 追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_email'])) {
    $new = trim($_POST['new_email']);
    if ($new && filter_var($new, FILTER_VALIDATE_EMAIL)) {
        if (!in_array($new, $emails)) {
            $emails[] = $new;
            saveAdminEmails($emails);
            $message = '追加しました';
        } else {
            $message = '既に登録されています';
        }
    } else {
        $message = '正しいメールアドレスを入力してください';
    }
}

// 削除 ?remove=xxx
if (isset($_GET['remove'])) {
    $rem = $_GET['remove'];
    $emails = array_values(array_filter($emails, function($e) use ($rem){ return $e !== $rem; }));
    saveAdminEmails($emails);
    $message = '削除しました';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>管理者通知メール設定</title>
<style>
body{font-family:sans-serif;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:8px}
.notice{color:green}
</style>
</head>
<body>
  <h1>管理者通知メールアドレス管理</h1>
  <?php if($message):?><p class="notice"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><?php endif; ?>

  <h2>登録済みメール</h2>
  <table><thead><tr><th>メール</th><th>操作</th></tr></thead><tbody>
    <?php if(empty($emails)): ?>
      <tr><td colspan="2">未登録</td></tr>
    <?php else: foreach($emails as $em): ?>
      <tr><td><?=htmlspecialchars($em,ENT_QUOTES,'UTF-8')?></td>
      <td><a href="?remove=<?=urlencode($em)?>" onclick="return confirm('削除しますか？')">削除</a></td></tr>
    <?php endforeach; endif; ?>
  </tbody></table>

  <h2>追加</h2>
  <form method="post" action="">
    <input type="email" name="new_email" required placeholder="admin@example.com">
    <button type="submit">追加</button>
  </form>

  <p><a href="admin_dashboard.php">管理画面へ戻る</a></p>
</body>
</html>
