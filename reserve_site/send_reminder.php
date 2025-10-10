<?php
/**
 * 前日リマインド送信スクリプト（CSV版・ISO-2022-JP対応）
 * - 予約者：明日が予約日の人に個別リマインド
 * - スタッフ：admin_mail.csv の全員に「明日分の予約まとめ」を1通で送信
 * - テスト：?days_ahead=0 を付けると当日分で実行（例：/send_reminder.php?days_ahead=0）
 */

mb_language("Japanese");
mb_internal_encoding("UTF-8");
date_default_timezone_set('Asia/Tokyo');

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

// ===== パス / 環境設定 =====
$BASE_DIR         = __DIR__;
$reservation_csv  = $BASE_DIR . "/data/reservations.csv";
$staff_csv        = $BASE_DIR . "/data/admin_mail.csv";
$from_address     = "no-reply@flat-amami-7790.fool.jp";
$site_url         = "https://flat-amami-7790.fool.jp/reserve_site";

// 対象日（既定=明日 / days_ahead=0 で当日）
// 例: /send_reminder.php?days_ahead=0
$days_ahead  = isset($_GET['days_ahead']) ? intval($_GET['days_ahead']) : 1;
if ($days_ahead < 0) $days_ahead = 0;
$target_date = date("Y-m-d", strtotime(($days_ahead === 0) ? "today" : "+{$days_ahead} day"));

// ===== 共通ヘルパ =====
function normalize_header($s) {
    // 先頭BOM除去 + 前後空白除去
    return preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$s));
}
function send_mail_iso2022jp($to, $subject, $body, $from) {
    $subject_enc = mb_convert_encoding($subject, "ISO-2022-JP", "UTF-8");
    $body_enc    = mb_convert_encoding($body, "ISO-2022-JP", "UTF-8");

    $headers  = "From: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=ISO-2022-JP\r\n";
    $headers .= "Content-Transfer-Encoding: 7bit";

    // 送信失敗は echo ログに残すだけで処理継続
    if (!@mb_send_mail($to, $subject_enc, $body_enc, $headers)) {
        echo "⚠️ 送信失敗: {$to}\n";
    }
}
function load_staff_emails($csv_path) {
    $emails = [];
    if (!file_exists($csv_path)) return $emails;

    if (($fp = fopen($csv_path, "r")) !== false) {
        // ヘッダー読み飛ばし
        $header = fgetcsv($fp);
        // データ行
        while (($row = fgetcsv($fp)) !== false) {
            // 想定: [名前, メールアドレス]
            $email = trim((string)($row[1] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        fclose($fp);
    }
    // 重複除去
    return array_values(array_unique($emails));
}
function open_csv_assoc_iter($csv_path) {
    // 予約CSVをヘッダー名→値の連想配列で返すイテレーション用
    // 想定ヘッダー: ID,日付,時間,企業名,連絡先,匿名,カテゴリ,備考,作成日時
    if (!file_exists($csv_path)) return [null, null];

    $fp = fopen($csv_path, "r");
    if (!$fp) return [null, null];

    $raw_header = fgetcsv($fp);
    if (!$raw_header) { fclose($fp); return [null, null]; }

    // ヘッダー名正規化
    $header = array_map('normalize_header', $raw_header);
    $h2i = []; // header -> index
    foreach ($header as $i => $h) $h2i[$h] = $i;

    $fetch = function() use ($fp, $h2i) {
        $row = fgetcsv($fp);
        if ($row === false) return false;

        $assoc = [];
        foreach ($h2i as $h => $i) {
            $assoc[$h] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }
        return $assoc;
    };

    return [$fp, $fetch];
}

// ===== メイン処理 =====

// スタッフ一覧の読み込み
$staff_addresses = load_staff_emails($staff_csv);

// 予約データの存在確認
if (!file_exists($reservation_csv)) {
    echo "予約データが見つかりません。\n";
    exit;
}

// 予約者宛リマインド + スタッフまとめ生成
list($fpRes, $nextRow) = open_csv_assoc_iter($reservation_csv);
if (!$fpRes || !$nextRow) {
    echo "予約CSVを読み込めませんでした。\n";
    exit;
}

$staff_summary = "";
$sent_count_user = 0;

while (($r = $nextRow()) !== false) {
    // 必要列を取り出し（列名は日本語ヘッダーを想定）
    $date      = $r['日付']     ?? '';
    $time      = $r['時間']     ?? '';
    $company   = $r['企業名']   ?? '';
    $email     = $r['連絡先']   ?? '';
    $anonymous = $r['匿名']     ?? '';
    $category  = $r['カテゴリ'] ?? '';
    $note      = $r['備考']     ?? '';

    if ($date === $target_date && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // --- 個別（予約者）リマインド ---
        $name_display = ($anonymous === "はい") ? "お客様" : "{$company} 様";
        $subject = "【予約リマインド】{$target_date}（{$time}）のご予約について";
        $body = <<<EOT
{$name_display}

いつもご利用ありがとうございます。
以下の内容でご予約をいただいております。

────────────────────
日時：{$target_date} {$time}
企業名：{$company}
カテゴリ：{$category}
────────────────────

当日はよろしくお願いします。

------------------------------------
予約サイト_URL
{$site_url}
------------------------------------
※このメールは自動送信されています。
EOT;

        send_mail_iso2022jp($email, $subject, $body, $from_address);
        $sent_count_user++;

        // --- スタッフまとめ（1通に集約） ---
        $staff_summary .= "・{$target_date} {$time}：{$company}（{$category}）\n";
        if ($note !== '') {
            $staff_summary .= "　備考：{$note}\n";
        }
        $staff_summary .= "\n";
    }
}
fclose($fpRes);

// スタッフ向けまとめ送信
if ($staff_summary !== '' && !empty($staff_addresses)) {
    $staff_subject = "【社内共有】{$target_date} の予約一覧（合計 " . substr_count($staff_summary, "・") . " 件）";
    $staff_body = <<<EOT
スタッフ各位

以下の通り、{$target_date} の予約があります。

────────────────────
{$staff_summary}────────────────────

ご確認をお願いいたします。

------------------------------------
Flat Amami 予約サイト 管理用
{$site_url}/admin.html
------------------------------------
※このメールは自動送信されています。
EOT;

    // カンマ区切りで一斉送信
    send_mail_iso2022jp(implode(",", $staff_addresses), $staff_subject, $staff_body, $from_address);
    echo "✅ スタッフまとめ送信済み（宛先 " . count($staff_addresses) . " 件）\n";
} else {
    echo "ℹ️ スタッフ宛まとめ：送信対象なし（予約0件 or 宛先未登録）。\n";
}

echo "✅ 予約者宛リマインド送信数：{$sent_count_user} 件\n";
echo "完了しました。（対象日: {$target_date} / days_ahead={$days_ahead}）\n";
