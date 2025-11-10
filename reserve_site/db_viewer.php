<?php
// db_viewer.php - SQLite簡易ビューワ
// reserve_site/data/reservation_system.db をブラウザで閲覧するだけのツール

header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Asia/Tokyo');

$dbPath = __DIR__ . '/data/reservation_system.db';

if (!file_exists($dbPath)) {
    exit("<h3>データベースが見つかりません: {$dbPath}</h3>");
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("<h3>DB接続エラー: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "</h3>");
}

// テーブル一覧を取得
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
              ->fetchAll(PDO::FETCH_COLUMN);

// 選択されたテーブル
$table = $_GET['table'] ?? ($tables[0] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>SQLiteビューワ</title>
<style>
body { font-family: sans-serif; background: #f7f7f7; padding: 20px; }
h1 { margin-bottom: 10px; }
table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 10px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 14px; }
th { background: #eee; }
a { color: #007acc; text-decoration: none; margin-right: 10px; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>

<h1>📊 SQLite データビューワ</h1>

<?php if (empty($tables)): ?>
<p>テーブルが存在しません。</p>
<?php else: ?>
<p>テーブルを選択してください：</p>
<p>
<?php foreach ($tables as $t): ?>
  <a href="?table=<?=urlencode($t)?>"><?=htmlspecialchars($t)?></a>
<?php endforeach; ?>
</p>

<?php if ($table): ?>
<hr>
<h2>📁 <?=htmlspecialchars($table)?></h2>
<?php
try {
    $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 500");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p>データはありません。</p>";
    } else {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars((string)$val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "<p>（最大500件まで表示）</p>";
    }
} catch (PDOException $e) {
    echo "<p>テーブル取得エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
<?php endif; ?>
<?php endif; ?>

</body>
</html>
