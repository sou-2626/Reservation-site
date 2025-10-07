<?php
require_once __DIR__ . '/config.php';

// 今日の日付
$today = date("Y-m-d");

// 明日の日付を算出
$tomorrow = date("Y-m-d", strtotime("+1 day"));

// 予約CSVを読み込み
if (!file_exists(RESERVATIONS_CSV)) {
    exit("予約データがありません。\n");
}

$fp = fopen(RESERVATIONS_CSV, 'r');
while (($row = fgetcsv($fp)) !== false) {
    list($id, $date, $company, $email, $time, $category, $anonymous, $createdAt) = $row;

    if ($date === $tomorrow) {
        // --- ユーザー向けリマインド ---
        $subject = "【リマインド】明日のご予約について";
        $body = "{$company} 様\n\n".
                "以下の内容で明日ご予約いただいております。\n\n".
                "日付: $date\n".
                "時間帯: $time\n".
                "カテゴリ: $category\n\n".
                "当日はよろしくお願いいたします。";

        sendMail($email, $subject, $body);

        // --- 管理者向けリマインド ---
        $adminSubject = "【管理者用リマインド】明日の予約: $date";
        $adminBody = "以下の予約が明日に予定されています。\n\n".
                     "企業名: $company\n".
                     "メール: $email\n".
                     "日付: $date\n".
                     "時間帯: $time\n".
                     "カテゴリ: $category\n".
                     "匿名表示: ".($anonymous ? "はい" : "いいえ")."\n";

        foreach (getAdminEmails() as $adminEmail) {
            sendMail($adminEmail, $adminSubject, $adminBody);
        }
    }
}
fclose($fp);

echo "リマインド送信完了\n";
