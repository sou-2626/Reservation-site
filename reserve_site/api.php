<?php

/**
 * 予約API（CSV保存版 / UTF-8 BOM / Excel想定） v2.1
 * - list    : GET  /api.php?action=list
 * - create  : POST /api.php?action=create  (JSON: {name,contact,date(YYYY-MM-DD),time,anonymous,category,note})
 * - update  : POST /api.php?action=update  (JSON: {id, date?, time?, category?, note?})
 * - delete  : POST /api.php?action=delete  (JSON: {id})
 * - blocked_list   : GET  /api.php?action=blocked_list
 * - blocked_add    : POST /api.php?action=blocked_add    (JSON: {date, reason})
 * - blocked_delete : POST /api.php?action=blocked_delete (JSON: {date})
 */

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo'); // ← 作成日時のズレ対策（サーバー時刻をJSTに固定）

// 画面へのWarning/Notice出力を止め、必ずJSONで返す
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

// ---- CSV ユーティリティ ----
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
    if (!$fp) err('CSVを作成できませんでした', 500);
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));  // BOM
    fputcsv($fp, csv_headers());
    fclose($fp);
    @chmod($file, 0664);
  }
}
function read_all_csv($file)
{
  ensure_csv_exists($file);
  $rows = [];
  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);
    if (!$header) {
      fclose($fp);
      return $rows;
    }
    if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // 先頭BOM除去
    foreach ($header as &$h) $h = trim($h);
    unset($h);

    while (($cols = fgetcsv($fp)) !== false) {
      if (count($cols) < count($header)) continue;
      $row = @array_combine($header, $cols);
      if ($row !== false) $rows[] = $row;
    }
    fclose($fp);
  }
  return $rows;
}
function write_all_csv($file, $rows)
{
  $tmp = $file . '.tmp';
  $fp = fopen($tmp, 'w');
  if (!$fp) return false;
  fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
  fputcsv($fp, csv_headers());
  foreach ($rows as $r) {
    $line = [];
    foreach (csv_headers() as $h) $line[] = $r[$h] ?? '';
    fputcsv($fp, $line);
  }
  fclose($fp);
  return @rename($tmp, $file);
}
function append_csv_row($file, $row)
{
  ensure_csv_exists($file);
  $fp = fopen($file, 'a');
  if (!$fp) return false;
  fputcsv($fp, $row);
  fclose($fp);
  return true;
}
function next_id($rows)
{
  $max = 0;
  foreach ($rows as $r) {
    $id = 0;
    if (isset($r['ID'])) $id = intval($r['ID']);
    elseif (isset($r["\xEF\xBB\xBF" . 'ID'])) $id = intval($r["\xEF\xBB\xBF" . 'ID']);
    elseif (isset($r['id'])) $id = intval($r['id']);
    if ($id > $max) $max = $id;
  }
  return $max + 1;
}

// ---- blocked CSV util ----
function ensure_blocked_exists($file)
{
  if (!file_exists($file)) {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    $fp = fopen($file, 'w');
    if ($fp) {
      fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
      fputcsv($fp, blocked_headers());
      fclose($fp);
      @chmod($file, 0664);
    }
  }
}
function read_all_blocked($file)
{
  ensure_blocked_exists($file);
  $rows = [];
  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);
    if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    while (($cols = fgetcsv($fp)) !== false) {
      $r = [];
      foreach ($header as $i => $h) $r[$h] = $cols[$i] ?? '';
      $rows[] = $r;
    }
    fclose($fp);
  }
  return $rows;
}
function write_all_blocked($file, $rows)
{
  ensure_blocked_exists($file);
  if (($fp = fopen($file, 'w')) === false) return false;
  fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
  fputcsv($fp, blocked_headers());
  foreach ($rows as $r) {
    fputcsv($fp, [
      $r['日付'] ?? '',
      $r['理由'] ?? '',
      $r['作成日時'] ?? '',
    ]);
  }
  fclose($fp);
  return true;
}
function is_blocked_date($file, $ymd)
{
  foreach (read_all_blocked($file) as $r) {
    if (($r['日付'] ?? '') === $ymd) return true;
  }
  return false;
}

// ---- アクション ----
$action = $_GET['action'] ?? '';

switch ($action) {

  case 'list': {
      $rows = read_all_csv($DATA_FILE);

      // 日本語/英語の両方に対応するエイリアス
      $aliases = [
        'id'         => ['ID', 'id'],
        'date'       => ['日付', 'date'],
        'time'       => ['時間', 'time'],
        'name'       => ['企業名', 'name'],
        'contact'    => ['連絡先', 'contact'],
        'anonymous'  => ['匿名', 'anonymous'],
        'category'   => ['カテゴリ', 'category'],
        'note'       => ['備考', 'note'],
        'created_at' => ['作成日時', 'created_at'],
      ];
      $get = function ($row, $key) use ($aliases) {
        foreach ($aliases[$key] as $k) {
          if (isset($row[$k])) return $row[$k];
          $bk = "\xEF\xBB\xBF" . $k;
          if (isset($row[$bk])) return $row[$bk];
        }
        return '';
      };

      $list = [];
      foreach ($rows as $r) {
        $list[] = [
          'id'         => intval($get($r, 'id')),
          'date'       => $get($r, 'date'),
          'time'       => $get($r, 'time'),
          'name'       => $get($r, 'name'),
          'contact'    => $get($r, 'contact'),
          'anonymous'  => $get($r, 'anonymous'),
          'category'   => $get($r, 'category'),
          'note'       => $get($r, 'note'),
          'created_at' => $get($r, 'created_at'),
        ];
      }
      ok($list);
    }

  case 'ping': {
    jsok(['ok'=>true,'name'=>'auth.php']);
  }


  case 'create': {
      $raw = file_get_contents('php://input');
      $payload = json_decode($raw, true);
      if (!is_array($payload)) err('JSONの形式が不正です');

      $name      = trim($payload['name'] ?? '');
      $contact   = trim($payload['contact'] ?? '');
      $date      = trim($payload['date'] ?? ''); // YYYY-MM-DD
      $time      = trim($payload['time'] ?? '');
      $anonymous = $payload['anonymous'] ?? '';
      $category  = trim($payload['category'] ?? '');
      $note      = trim($payload['note'] ?? '');

      if ($name === '' || $date === '' || $time === '' || $category === '') {
        $missing = [];
        if ($name === '')     $missing[] = 'name';
        if ($date === '')     $missing[] = 'date';
        if ($time === '')     $missing[] = 'time';
        if ($category === '') $missing[] = 'category';
        err('必須項目が不足: ' . implode(', ', $missing));
      }
      // （任意）予約不可日のチェックをUIで済ませているが、サーバ側で弾きたい場合は次を有効化
      // if (is_blocked_date($BLOCK_FILE, $date)) err('この日は予約不可です', 400);

      $rows = read_all_csv($DATA_FILE);
      $id = next_id($rows);
      $created_at = date('Y-m-d H:i:s'); // JSTで記録

      $row = [
        strval($id),
        $date,
        $time,
        $name,
        $contact,
        (is_bool($anonymous) ? ($anonymous ? 'はい' : 'いいえ') : strval($anonymous)),
        $category,
        $note,
        $created_at,
      ];

      if (!append_csv_row($DATA_FILE, $row)) err('書き込みに失敗しました', 500);
      ok(['id' => $id]);
    }

  case 'update': { // ← 追加：一部項目の更新
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSONの形式が不正です');

      $id  = intval($p['id'] ?? 0);
      $date = isset($p['date']) ? trim($p['date']) : null;       // 省略時は変更しない
      $time = isset($p['time']) ? trim($p['time']) : null;
      $category = isset($p['category']) ? trim($p['category']) : null;
      $note = isset($p['note']) ? trim($p['note']) : null;

      if ($id <= 0) err('IDが不正です');
      if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付はYYYY-MM-DDで指定してください');

      $rows = read_all_csv($DATA_FILE);
      $found = false;

      foreach ($rows as &$r) {
        $rid = 0;
        if (isset($r['ID'])) $rid = intval($r['ID']);
        elseif (isset($r["\xEF\xBB\xBF" . 'ID'])) $rid = intval($r["\xEF\xBB\xBF" . 'ID']);
        elseif (isset($r['id'])) $rid = intval($r['id']);

        if ($rid === $id) {
          $found = true;
          if ($date !== null)     $r['日付']   = $date;
          if ($time !== null)     $r['時間']   = $time;     // 午前/午後など任意文字列でOK
          if ($category !== null) $r['カテゴリ'] = $category;
          if ($note !== null)     $r['備考']   = $note;
          // 作成日時は変更しない
          break;
        }
      }
      unset($r);

      if (!$found) err('指定のIDが見つかりませんでした', 404);
      if (!write_all_csv($DATA_FILE, $rows)) err('更新の書き込みに失敗しました', 500);
      ok();
    }

  case 'delete': {
      $raw = file_get_contents('php://input');
      $payload = json_decode($raw, true);
      if (!is_array($payload)) err('JSONの形式が不正です');

      $id = intval($payload['id'] ?? 0);
      if ($id <= 0) err('IDが不正です');

      $rows = read_all_csv($DATA_FILE);
      $found = false;
      $filtered = [];
      foreach ($rows as $r) {
        $rid = 0;
        if (isset($r['ID'])) $rid = intval($r['ID']);
        elseif (isset($r["\xEF\xBB\xBF" . 'ID'])) $rid = intval($r["\xEF\xBB\xBF" . 'ID']);
        elseif (isset($r['id'])) $rid = intval($r['id']);
        if ($rid === $id) {
          $found = true;
          continue;
        }
        $filtered[] = $r;
      }
      if (!$found) err('指定のIDが見つかりませんでした', 404);
      if (!write_all_csv($DATA_FILE, $filtered)) err('削除の書き込みに失敗しました', 500);
      ok();
    }

  case 'blocked_list': {
      $rows = read_all_blocked($BLOCK_FILE);
      $out = [];
      foreach ($rows as $r) {
        $out[] = [
          'date' => $r['日付'] ?? '',
          'reason' => $r['理由'] ?? '',
        ];
      }
      echo json_encode($out, JSON_UNESCAPED_UNICODE);
      exit;
    }

  case 'blocked_add': {
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSONの形式が不正です');
      $date = trim($p['date'] ?? '');
      $reason = trim($p['reason'] ?? '');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付はYYYY-MM-DDで指定してください');

      $list = read_all_blocked($BLOCK_FILE);
      foreach ($list as $r) if (($r['日付'] ?? '') === $date) ok(); // 既存ならOK扱い
      $list[] = [
        '日付' => $date,
        '理由' => $reason,
        '作成日時' => date('Y-m-d H:i:s'),
      ];
      if (!write_all_blocked($BLOCK_FILE, $list)) err('書き込みに失敗しました', 500);
      ok();
    }

  case 'blocked_delete': {
      $raw = file_get_contents('php://input');
      $p = json_decode($raw, true);
      if (!is_array($p)) err('JSONの形式が不正です');
      $date = trim($p['date'] ?? '');
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付はYYYY-MM-DDで指定してください');

      $list = read_all_blocked($BLOCK_FILE);
      $filtered = [];
      foreach ($list as $r) if (($r['日付'] ?? '') !== $date) $filtered[] = $r;
      if (!write_all_blocked($BLOCK_FILE, $filtered)) err('削除に失敗しました', 500);
      ok();
    }

  default:
    err('未知のactionです');
}
