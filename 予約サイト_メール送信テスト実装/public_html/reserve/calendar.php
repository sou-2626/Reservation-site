<?php
require_once __DIR__ . '/config.php';

// 今日の日付
$year = date("Y");
$month = date("n");

// 指定された月があれば使う
if (isset($_GET['y']) && isset($_GET['m'])) {
    $year = (int)$_GET['y'];
    $month = (int)$_GET['m'];
}

// 月初と末日
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date("t", $firstDay);
$startWeek = date("w", $firstDay);

// 予約不可日を読み込み
$blockedDates = [];
if (file_exists(BLOCKED_DATES_CSV)) {
    if (($fp = fopen(BLOCKED_DATES_CSV, 'r')) !== false) {
        while (($row = fgetcsv($fp)) !== false) {
            $blockedDates[] = $row[0];
        }
        fclose($fp);
    }
}

// 予約済み情報を読み込み
$reserved = [];
if (file_exists(RESERVATIONS_CSV)) {
    if (($fp = fopen(RESERVATIONS_CSV, 'r')) !== false) {
        while (($row = fgetcsv($fp)) !== false) {
            $date = $row[1]; // 予約日
            $company = $row[2]; // 企業名
            $anonymous = $row[6]; // 匿名フラグ
            $reserved[$date] = ($anonymous === "1") ? "匿名" : $company;
        }
        fclose($fp);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>予約カレンダー</title>
  <style>
    body { font-family: sans-serif; }
    table { border-collapse: collapse; width: 100%; max-width: 700px; margin: auto; }
    th, td { border: 1px solid #999; text-align: center; padding: 10px; }
    th { background: #eee; }
    .today { background: #ffeb3b; }
    .blocked { background: #f8d7da; color: #721c24; }
    .reserved { background: #d4edda; color: #155724; }
    a { text-decoration: none; color: inherit; }
    .nav { text-align: center; margin: 20px; }
  </style>
</head>
<body>
  <h1 style="text-align:center;">予約カレンダー</h1>
  <div class="nav">
    <a href="?y=<?= $year ?>&m=<?= $month - 1 ?>">前の月</a> |
    <a href="?y=<?= $year ?>&m=<?= $month + 1 ?>">次の月</a>
  </div>
  <table>
    <tr>
      <th>日</th><th>月</th><th>火</th><th>水</th><th>木</th><th>金</th><th>土</th>
    </tr>
    <tr>
    <?php
    $day = 1;
    $w = 0;
    // 空白マス
    for ($i = 0; $i < $startWeek; $i++) {
        echo "<td></td>";
        $w++;
    }
    while ($day <= $daysInMonth) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $classes = [];
        $label = $day;
        $link = "reservation_form.php?date=$dateStr";

        if ($dateStr == date("Y-m-d")) {
            $classes[] = "today";
        }
        if (in_array($dateStr, $blockedDates)) {
            $classes[] = "blocked";
            $label .= "<br>予約不可";
            $link = null;
        }
        if (isset($reserved[$dateStr])) {
            $classes[] = "reserved";
            $label .= "<br>" . htmlspecialchars($reserved[$dateStr]);
            $link = null;
        }

        $classStr = empty($classes) ? "" : " class='" . implode(" ", $classes) . "'";
        echo "<td$classStr>";
        if ($link) {
            echo "<a href='$link'>$label</a>";
        } else {
            echo $label;
        }
        echo "</td>";

        $day++;
        $w++;
        if ($w % 7 == 0) {
            echo "</tr><tr>";
        }
    }
    // 残り空白
    while ($w % 7 != 0) {
        echo "<td></td>";
        $w++;
    }
    ?>
    </tr>
  </table>
  <div class="nav">
    <a href="index.php">トップへ戻る</a>
  </div>
</body>
</html>
