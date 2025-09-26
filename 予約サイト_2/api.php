<?php

/**
 * 予約API（CSV保存版 / UTF-8 BOM / Excel想定） v2
 * - list   : GET  /api.php?action=list
 * - create : POST /api.php?action=create  (JSON: {name,contact,date(YYYY-MM-DD),time,anonymous,category,note})
 * - delete : POST /api.php?action=delete  (JSON: {id})
 */

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

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

$DATA_FILE = __DIR__ . '/data/reservations.csv';

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
  // Excelで見やすい日本語見出し
  return ['ID', '日付', '時間', '企業名', '連絡先', '匿名', 'カテゴリ', '備考', '作成日時'];
}

function ensure_csv_exists($file)
{
  if (!file_exists($file)) {
    $fp = fopen($file, 'w');
    if (!$fp) err('CSVを作成できませんでした', 500);
    // UTF-8 BOM
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    // header
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
    // ヘッダ
    $header = fgetcsv($fp);
    if (!$header) {
      fclose($fp);
      return $rows;
    }

    // 先頭カラム名のBOM除去＋トリム
    if (isset($header[0])) {
      $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    foreach ($header as &$h) {
      $h = trim($h);
    }
    unset($h);

    while (($cols = fgetcsv($fp)) !== false) {
      if (count($cols) < count($header)) continue; // 不完全行はスキップ
      $row = @array_combine($header, $cols);
      if ($row === false) continue;
      $rows[] = $row;
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
    foreach (csv_headers() as $h) {
      $line[] = isset($r[$h]) ? $r[$h] : '';
    }
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
  // 追記のみ：BOMは不要
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
    elseif (isset($r['id'])) $id = intval($r['id']); // 英語ヘッダの互換
    if ($id > $max) $max = $id;
  }
  return $max + 1;
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
          $bk = "\xEF\xBB\xBF" . $k; // BOM付きキーも保険
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


  case 'create': {
      $raw = file_get_contents('php://input');
      $payload = json_decode($raw, true);
      if (!is_array($payload)) err('JSONの形式が不正です');

      $name      = trim($payload['name'] ?? '');
      $contact   = trim($payload['contact'] ?? '');
      $date      = trim($payload['date'] ?? ''); // YYYY-MM-DD
      $time      = trim($payload['time'] ?? '');
      $anonymous = $payload['anonymous'] ?? '';  // true/false or "はい/いいえ"
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
        $created_at,
      ];

      if (!append_csv_row($DATA_FILE, $row)) {
        err('書き込みに失敗しました', 500);
      }
      ok(['id' => $id]);
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
        elseif (isset($r['id'])) $rid = intval($r['id']); // 互換
        if ($rid === $id) {
          $found = true;
          continue;
        }
        $filtered[] = $r;
      }
      if (!$found) err('指定のIDが見つかりませんでした', 404);

      if (!write_all_csv($DATA_FILE, $filtered)) {
        err('削除の書き込みに失敗しました', 500);
      }
      ok();
    }


  default:
    err('未知のactionです');
}
