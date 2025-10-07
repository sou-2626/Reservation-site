<?php
// config.php

// ===============================
// 管理者ログイン情報（全員共通）
// ===============================
define('ADMIN_ID', 'admin');
define('ADMIN_PASS', 'password123');

// ===============================
// データ保存用CSVファイルパス
// ===============================
define('RESERVATIONS_CSV', __DIR__ . '/data/reservations.csv');
define('BLOCKED_DATES_CSV', __DIR__ . '/data/blocked_dates.csv');
define('ADMIN_EMAILS_CSV', __DIR__ . '/data/admin_emails.csv');

// ===============================
// メール送信設定
// ===============================
// 差出人アドレス（ロリポップの独自ドメインメール推奨）
define('MAIL_FROM', 'no-reply@example.com');
define('MAIL_FROM_NAME', '予約システム');

// メール送信時のエンコード（mb_send_mail利用）
mb_language("Japanese");
mb_internal_encoding("UTF-8");

/**
 * 管理者メールアドレス一覧を取得
 */
function getAdminEmails() {
    $emails = [];
    if (file_exists(ADMIN_EMAILS_CSV)) {
        $fp = fopen(ADMIN_EMAILS_CSV, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            if (!empty($row[0])) {
                $emails[] = trim($row[0]);
            }
        }
        fclose($fp);
    }
    return $emails;
}

/**
 * 管理者メールアドレスを保存
 * @param array $emails
 */
function saveAdminEmails($emails) {
    $fp = fopen(ADMIN_EMAILS_CSV, 'w');
    foreach ($emails as $email) {
        fputcsv($fp, [$email]);
    }
    fclose($fp);
}

/**
 * メール送信共通処理
 */
function sendMail($to, $subject, $body) {
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">";
    return mb_send_mail($to, $subject, $body, $headers);
}
