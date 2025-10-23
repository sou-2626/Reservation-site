<?php
mb_language("Japanese");
mb_internal_encoding("UTF-8");
date_default_timezone_set('Asia/Tokyo');

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/reservation_system.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("DB接続エラー: " . $e->getMessage());
}

// 今日〜10日後までの範囲を取得
$today = date('Y-m-d');
$limit = date('Y-m-d', strtotime('+10 days'));

// 範囲内の予約を取得
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE date BETWEEN ? AND ?");
$stmt->execute([$today, $limit]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 自動送信元設定 ---
$domain = $_SERVER['SERVER_NAME'] ?? '';
if (strpos($domain, 'flat-amami-7790.fool.jp') !== false) {
    $from = 'no-reply@flat-amami-7790.fool.jp';
} elseif (!empty($domain)) {
    $from = "no-reply@{$domain}";
} else {
    $from = 'no-reply@localhost';
}

$headers = "From: {$from}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

if (!$reservations) {
    exit("10日以内の予約はありません。\n");
}

$count = 0;

foreach ($reservations as $res) {
    // ✅ 管理者への送信は削除済み

    // 予約者のみ送信
    if (filter_var($res['contact'], FILTER_VALIDATE_EMAIL)) {
        $userSubject = "【ご予約確認】{$res['date']} {$res['time']} のご予約について";
        $userMessage = "{$res['name']} 様\n\n"
                     . "以下の内容でご予約を承っております。\n\n"
                     . "━━━━━━━━━━━━━━━━━━\n"
                     . "■ 日付：{$res['date']}\n"
                     . "■ 時間：{$res['time']}\n"
                     . "■ カテゴリ：{$res['category']}\n"
                     . "━━━━━━━━━━━━━━━━━━\n\n"
                     . "当日お待ちしております。\n"
                     . "このメールは自動送信されています。";

        mb_send_mail($res['contact'], $userSubject, $userMessage, $headers);
        $count++;
    }
}

echo "送信完了：{$count}件の予約者に通知を送信しました。\n";
?>
