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

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE date = ?");
$stmt->execute([$tomorrow]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mailStmt = $pdo->query("SELECT email FROM admin_mail");
$adminEmails = array_column($mailStmt->fetchAll(PDO::FETCH_ASSOC), 'email');

$from = 'no-reply@flat-amami-7790.fool.jp';

// UTF-8で送るように明示
$headers = "From: {$from}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

if (!$reservations) {
    exit("予約なし\n");
}

foreach ($reservations as $res) {
    $subject = "【予約リマインダー】{$res['date']} {$res['time']} のご予約予定";
    $message = "以下の予約が、明日予定されています。\n\n"
             . "━━━━━━━━━━━━━━━━━━\n"
             . "■ 日付：{$res['date']}\n"
             . "■ 時間：{$res['time']}\n"
             . "■ 企業名：{$res['name']}\n"
             . "■ 連絡先：{$res['contact']}\n"
             . "■ カテゴリ：{$res['category']}\n"
             . "■ 匿名：{$res['anonymous']}\n"
             . "■ 備考：{$res['note']}\n"
             . "━━━━━━━━━━━━━━━━━━\n\n"
             . "内容をご確認のうえ、ご対応をお願いいたします。\n"
             . "このメールは自動送信されています。返信は不要です。";

    foreach ($adminEmails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            mb_send_mail($email, $subject, $message, $headers);
        }
    }

    // 予約者にも同じ構成で送信（文言だけ少し変える）
    if (filter_var($res['contact'], FILTER_VALIDATE_EMAIL)) {
        $userSubject = "【ご予約前日リマインダー】{$res['date']} {$res['time']} のご予約について";
        $userMessage = "{$res['name']} 様\n\n"
                     . "明日、以下の内容でご予約をいただいております。\n\n"
                     . "━━━━━━━━━━━━━━━━━━\n"
                     . "■ 日付：{$res['date']}\n"
                     . "■ 時間：{$res['time']}\n"
                     . "■ カテゴリ：{$res['category']}\n"
                     . "■ 備考：{$res['note']}\n"
                     . "━━━━━━━━━━━━━━━━━━\n\n"
                     . "当日お待ちしております。\n"
                     . "このメールは自動送信されています。";

        mb_send_mail($res['contact'], $userSubject, $userMessage, $headers);
    }
}

echo "送信完了\n";
