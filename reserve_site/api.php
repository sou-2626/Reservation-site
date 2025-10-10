<?php

/**
 * 予約API（CSV保存版 / UTF-8 BOM / Excel想定） v2.3
 * - list    : GET  /api.php?action=list
 * - create  : POST /api.php?action=create
 * - update  : POST /api.php?action=update
 * - delete  : POST /api.php?action=delete
 * - blocked_list   : GET  /api.php?action=blocked_list
 * - blocked_add    : POST /api.php?action=blocked_add
 * - blocked_delete : POST /api.php?action=blocked_delete
 * - mail_list, mail_add, mail_delete : 管理者メール管理
 */

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => "PHPエラー: $errstr ($errfile:$errline)"
  ], JSON_UNESCAPED_UNICODE);
  exit;
});

$DATA_FILE  = __DIR__ . '/data/reservations.csv';
$BLOCK_FILE = __DIR__ . '/data/blocked_dates.csv';
$MAIL_FILE  = __DIR__ . '/data/admin_mail.csv';

// ---- 共通レスポンス ----
function ok($data = null)
{
  echo json_encode($data ?? ['ok' => true], JSON_UNESCAPED_UNICODE);
  exit;
}
function err($msg, $code = 400)
{
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// ---- CSVユーティリティ ----
function csv_headers()
{
  return ['ID', '日付', '時間', '企業名', '連絡先', '匿名', 'カテゴリ', '備考', '作成日時'];
}
function blocked_headers()
{
  return ['日付', '理由', '作成日時'];
}

function ensure_csv_exists($file)
{
  if (!file_exists($file)) {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    $fp = fopen($file, 'w');
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    if (str_ends_with($file, 'blocked_dates.csv')) fputcsv($fp, blocked_headers());
    else fputcsv($fp, csv_headers());
    fclose($fp);
  }
}

function read_all_csv($file)
{
  ensure_csv_exists($file);
  $rows = [];
  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);
    if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    while (($cols = fgetcsv($fp)) !== false) {
      if (count($cols) >= count($header)) $rows[] = @array_combine($header, $cols);
    }
    fclose($fp);
  }
  return $rows;
}

function append_csv_row($file, $row)
{
  ensure_csv_exists($file);
  $fp = fopen($file, 'a');
  fputcsv($fp, $row);
  fclose($fp);
  return true;
}

function next_id($rows)
{
  $max = 0;
  foreach ($rows as $r) {
    $id = intval($r['ID'] ?? 0);
    if ($id > $max) $max = $id;
  }
  return $max + 1;
}

// ---- メール送信共通関数 ----
function send_mail_iso2022jp($to, $subject, $body, $from)
{
  $subject_enc = mb_convert_encoding($subject, "ISO-2022-JP", "UTF-8");
  $body_enc    = mb_convert_encoding($body, "ISO-2022-JP", "UTF-8");
  $headers  = "From: {$from}\r\n";
  $headers .= "Content-Type: text/plain; charset=ISO-2022-JP\r\n";
  $headers .= "Content-Transfer-Encoding: 7bit";
  @mb_send_mail($to, $subject_enc, $body_enc, $headers);
}

// --- admin_mail.csv を作る（ヘッダーは「名前,メールアドレス」） ---
function ensure_mail_exists($file)
{
  if (!file_exists($file)) {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    $fp = fopen($file, 'w');
    if (!$fp) return false;
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($fp, ['名前', 'メールアドレス']);
    fclose($fp);
    @chmod($file, 0664);
  }
  return true;
}

// --- 末尾が改行で終わっていなければ改行を挿入 ---
function ensure_newline_at_eof($file, $appendHandle)
{
  clearstatcache(true, $file);
  $size = @filesize($file);
  if ($size === false || $size === 0) return;
  $rfp = fopen($file, 'rb');
  if (!$rfp) return;
  fseek($rfp, -1, SEEK_END);
  $last = fread($rfp, 1);
  fclose($rfp);
  if ($last !== "\n") {
    fwrite($appendHandle, PHP_EOL);
  }
}

// ---- アクション ----
$action = $_GET['action'] ?? '';

switch ($action) {

  // --- 動作確認用 ping ---
  case 'ping': {
      echo json_encode(['ok' => true, 'name' => 'api.php'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    // ===============================
    // 予約一覧
    // ===============================
  case 'list': {
      $rows = read_all_csv($DATA_FILE);
      $out = [];
      foreach ($rows as $r) {
        $out[] = [
          'id' => intval($r['ID'] ?? 0),
          'date' => $r['日付'] ?? '',
          'time' => $r['時間'] ?? '',
          'name' => $r['企業名'] ?? '',
          'contact' => $r['連絡先'] ?? '',
          'anonymous' => $r['匿名'] ?? '',
          'category' => $r['カテゴリ'] ?? '',
          'note' => $r['備考'] ?? '',
          'created_at' => $r['作成日時'] ?? ''
        ];
      }
      ok($out);
    }

    // ===============================
    // 新規予約作成（メール通知付き）
    // ===============================
  case 'create': {
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSON形式が不正です');

      $name = trim($p['name'] ?? '');
      $contact = trim($p['contact'] ?? '');
      $date = trim($p['date'] ?? '');
      $time = trim($p['time'] ?? '');
      $anonymous = $p['anonymous'] ?? '';
      $category = trim($p['category'] ?? '');
      $note = trim($p['note'] ?? '');

      if ($name === '' || $date === '' || $time === '' || $category === '') {
        err('必須項目が不足しています');
      }

      $rows = read_all_csv($DATA_FILE);
      $id = next_id($rows);
      $created_at = date('Y-m-d H:i:s');
      $row = [
        strval($id),
        $date,
        $time,
        $name,
        $contact,
        (is_bool($anonymous) ? ($anonymous ? 'はい' : 'いいえ') : strval($anonymous)),
        $category,
        $note,
        $created_at
      ];
      append_csv_row($DATA_FILE, $row);

      // ===== 予約完了メール（予約者宛） =====
      if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $from = "no-reply@flat-amami-7790.fool.jp";
        $site = "https://flat-amami-7790.fool.jp/reserve_site";
        $subject = "【予約完了】{$date} {$time} のご予約を受け付けました";
        $body = <<<EOT
{$name} 様

ご予約ありがとうございます。
以下の内容で受け付けました。

────────────────────
日時：{$date} {$time}
企業名：{$name}
カテゴリ：{$category}
────────────────────

当日お待ちしております。

------------------------------------
Flat Amami 予約サイト
{$site}
------------------------------------
※このメールは自動送信されています。
EOT;
        send_mail_iso2022jp($contact, $subject, $body, $from);
      }

      // ===== スタッフ通知 =====
      if (file_exists($MAIL_FILE) && ($fp = fopen($MAIL_FILE, "r")) !== false) {
        $header = fgetcsv($fp);
        $staff_to = [];
        while (($row2 = fgetcsv($fp)) !== false) {
          if (!empty($row2[1]) && filter_var($row2[1], FILTER_VALIDATE_EMAIL)) {
            $staff_to[] = $row2[1];
          }
        }
        fclose($fp);
        if (!empty($staff_to)) {
          $subject_s = "【新規予約】{$date} {$time}：{$name}（{$category}）";
          $body_s = <<<EOT
新しい予約が登録されました。

日時：{$date} {$time}
企業名：{$name}
カテゴリ：{$category}
備考：{$note}

管理画面：
https://flat-amami-7790.fool.jp/reserve_site/admin.html

※このメールは自動送信されています。
EOT;
          send_mail_iso2022jp(implode(",", $staff_to), $subject_s, $body_s, "no-reply@flat-amami-7790.fool.jp");
        }
      }

      ok(['id' => $id]);
    }

    // ===============================
    // 予約更新
    // ===============================
  case 'update': {
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSONの形式が不正です');
      $id  = intval($p['id'] ?? 0);
      if ($id <= 0) err('IDが不正です');

      $rows = read_all_csv($DATA_FILE);
      $found = false;
      foreach ($rows as &$r) {
        if (intval($r['ID'] ?? 0) === $id) {
          $found = true;
          foreach (['日付' => 'date', '時間' => 'time', 'カテゴリ' => 'category', '備考' => 'note'] as $colJp => $colEn) {
            if (isset($p[$colEn])) $r[$colJp] = trim($p[$colEn]);
          }
          break;
        }
      }
      unset($r);
      if (!$found) err('指定のIDが見つかりません');
      $tmp = $DATA_FILE . '.tmp';
      $fp = fopen($tmp, 'w');
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, csv_headers());
      foreach ($rows as $r) fputcsv($fp, $r);
      fclose($fp);
      rename($tmp, $DATA_FILE);
      ok();
    }

    // ===============================
    // 予約削除
    // ===============================
  case 'delete': {
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSON形式が不正です');
      $id = intval($p['id'] ?? 0);
      if ($id <= 0) err('IDが不正です');

      $rows = read_all_csv($DATA_FILE);
      $newRows = array_filter($rows, fn($r) => intval($r['ID'] ?? 0) !== $id);
      $tmp = $DATA_FILE . '.tmp';
      $fp = fopen($tmp, 'w');
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, csv_headers());
      foreach ($newRows as $r) fputcsv($fp, $r);
      fclose($fp);
      rename($tmp, $DATA_FILE);
      ok();
    }

    // ===============================
    // 予約不可日管理
    // ===============================
  case 'blocked_list': {
      $rows = read_all_csv($BLOCK_FILE);
      $out = [];
      foreach ($rows as $r) $out[] = ['date' => $r['日付'] ?? '', 'reason' => $r['理由'] ?? ''];
      ok($out);
    }

  case 'blocked_add': {
      $p = json_decode(file_get_contents('php://input'), true);
      $date = trim($p['date'] ?? '');
      $reason = trim($p['reason'] ?? '');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付形式が不正です');
      $list = read_all_csv($BLOCK_FILE);
      foreach ($list as $r) if (($r['日付'] ?? '') === $date) ok();
      $list[] = ['日付' => $date, '理由' => $reason, '作成日時' => date('Y-m-d H:i:s')];
      $fp = fopen($BLOCK_FILE, 'w');
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, blocked_headers());
      foreach ($list as $r) fputcsv($fp, [$r['日付'], $r['理由'], $r['作成日時']]);
      fclose($fp);
      ok();
    }

  case 'blocked_delete': {
      $p = json_decode(file_get_contents('php://input'), true);
      $date = trim($p['date'] ?? '');
      $list = read_all_csv($BLOCK_FILE);
      $new = array_filter($list, fn($r) => ($r['日付'] ?? '') !== $date);
      $fp = fopen($BLOCK_FILE, 'w');
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, blocked_headers());
      foreach ($new as $r) fputcsv($fp, [$r['日付'], $r['理由'], $r['作成日時']]);
      fclose($fp);
      ok();
    }

    // ===============================
    // 管理者メール管理（修正版）
    // ===============================
  case 'mail_list': {
      ensure_mail_exists($MAIL_FILE);
      $rows = [];
      if (($fp = fopen($MAIL_FILE, 'r')) !== false) {
        $header = fgetcsv($fp); // ヘッダを読み飛ばす
        if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        while (($cols = fgetcsv($fp)) !== false) {
          if (count($cols) >= 2) $rows[] = ['name' => $cols[0], 'email' => $cols[1]];
        }
        fclose($fp);
      }
      ok($rows);
    }

  case 'mail_add': {
      $input = json_decode(file_get_contents('php://input'), true);
      $name  = trim($input['name']  ?? '');
      $email = trim($input['email'] ?? '');
      if ($name === '' || $email === '') err('名前とメールアドレスは必須です');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('メールアドレスの形式が不正です');

      ensure_mail_exists($MAIL_FILE);
      $fp = fopen($MAIL_FILE, 'a');
      if (!$fp) err('ファイルに書き込めません', 500);

      // 改行がないときは改行を入れる
      ensure_newline_at_eof($MAIL_FILE, $fp);

      fputcsv($fp, [$name, $email]);
      fclose($fp);
      ok(['message' => '登録しました']);
    }

  case 'mail_delete': {
      $input = json_decode(file_get_contents('php://input'), true);
      $email = trim($input['email'] ?? '');
      if ($email === '') err('メールアドレスが指定されていません');

      ensure_mail_exists($MAIL_FILE);
      $rows = [];
      $fp = fopen($MAIL_FILE, 'r');
      $header = fgetcsv($fp); // ヘッダ読み飛ばし
      while (($cols = fgetcsv($fp)) !== false) {
        if (count($cols) >= 2 && trim($cols[1]) !== $email) $rows[] = $cols;
      }
      fclose($fp);

      $fp = fopen($MAIL_FILE, 'w');
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, ['名前', 'メールアドレス']);
      foreach ($rows as $r) fputcsv($fp, $r);
      fclose($fp);

      ok(['message' => '削除しました']);
    }

  default:
    err('未知のactionです');
}
