<?php
// send_reminder.php - SQLite版（ダミー送信例）

mb_language("Japanese");
mb_internal_encoding("UTF-8");

date_default_timezone_set('Asia/Tokyo');

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/reserve_site/data/reservation_system.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("DB接続エラー: " . $e->getMessage());
}

// 翌日の予約を取得
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE date = ?");
$stmt->execute([$tomorrow]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 管理者メールアドレス一覧
$mailStmt = $pdo->query("SELECT email FROM admin_mail");
$adminEmails = array_column($mailStmt->fetchAll(PDO::FETCH_ASSOC), 'email');

foreach ($reservations as $res) {
    $subject = "【予約リマインダー】{$res['date']} {$res['time']} の予約";
    $message = "以下の内容で予約が登録されています：\n"
             . "日付：{$res['date']}\n"
             . "時間：{$res['time']}\n"
             . "企業名：{$res['name']}\n"
             . "連絡先：{$res['contact']}\n"
             . "カテゴリ：{$res['category']}\n"
             . "匿名：{$res['anonymous']}\n"
             . "備考：{$res['note']}\n";

    foreach ($adminEmails as $email) {
        // 本番運用では以下を使用
        // mb_send_mail($email, $subject, $message);
        echo "送信先: $email\n件名: $subject\n本文:\n$message\n---\n";
    }
}
