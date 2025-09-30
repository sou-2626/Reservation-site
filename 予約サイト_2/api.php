<?php
/**
 * 予約API（CSV保存版 / UTF-8 BOM / Excel想定） v2.1
 *
 * - list           : GET  /api.php?action=list
 * - create         : POST /api.php?action=create
 *                    JSON: {name, contact, date(YYYY-MM-DD), time, anonymous, category, note}
 *                    予約不可日および当日/翌日の予約はサーバー側でブロック
 * - delete         : POST /api.php?action=delete
 *                    JSON: {id}
 * - blocked_list   : GET  /api.php?action=blocked_list
 * - blocked_add    : POST /api.php?action=blocked_add   JSON: {date(YYYY-MM-DD)}
 * - blocked_delete : POST /api.php?action=blocked_delete JSON: {date(YYYY-MM-DD)}
 */

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// 画面へのWarning/Notice出力を止め、必ずJSONで返す
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  http_response_code(500);
  echo json_encode([
    'ok'    => false,
    'error' => "PHPエラー: $errstr ($errfile:$errline)"
  ], JSON_UNESCAPED_UNICODE);
  exit;
});

// ===== パス定義 =====
$DATA_FILE  = __DIR__ . '/data/reservations.csv';
$BLOCK_FILE = __DIR__ . '/data/blocked_dates.csv';

// ===== 共通レスポンス =====
function ok($data = null) {
  echo json_encode($data ?? ['ok' => true], JSON_UNESCAPED_UNICODE);
  exit;
}
function err($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== ディレクトリ保証 =====
function ensure_data_dir() {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

// ======== 予約CSV（一覧/作成/削除で使用） ========
function csv_headers() {
  return ['ID', '日付', '時間', '企業名', '連絡先', '匿名', 'カテゴリ', '備考', '作成日時'];
}
function ensure_csv_exists($file) {
  ensure_data_dir();
  if (!file_exists($file)) {
    $fp = fopen($file, 'w');
    if (!$fp) err('CSVを作成できませんでした', 500);
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
    fputcsv($fp, csv_headers());
    fclose($fp);
    @chmod($file, 0664);
  }
}
function read_all_csv($file) {
  ensure_csv_exists($file);
  $rows = [];
  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);
    if (!$header) { fclose($fp); return $rows; }
    if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // BOM除去
    foreach ($header as &$h) $h = trim($h); unset($h);
    while (($cols = fgetcsv($fp)) !== false) {
      if (count($cols) < count($header)) continue;
      $row = @array_combine($header, $cols);
      if ($row !== false) $rows[] = $row;
    }
    fclose($fp);
  }
  return $rows;
}
function write_all_csv($file, $rows) {
  ensure_data_dir();
  $tmp = $file . '.tmp';
  $fp = fopen($tmp, 'w');
  if (!$fp) return false;
  fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
  fputcsv($fp, csv_headers());
  foreach ($rows as $r) {
    $line = [];
    foreach (csv_headers() as $h) $line[] = isset($r[$h]) ? $r[$h] : '';
    fputcsv($fp, $line);
  }
  fclose($fp);
  return @rename($tmp, $file);
}
function append_csv_row($file, $row) {
  ensure_csv_exists($file);
  $fp = fopen($file, 'a');
  if (!$fp) return false;
  fputcsv($fp, $row); // 追記はBOM不要
  fclose($fp);
  return true;
}
function next_id($rows) {
  $max = 0;
  foreach ($rows as $r) {
    $id = 0;
    if (isset($r['ID'])) $id = intval($r['ID']);
    elseif (isset($r["\xEF\xBB\xBF" . 'ID'])) $id = intval($r["\xEF\xBB\xBF" . 'ID']);
    elseif (isset($r['id'])) $id = intval($r['id']); // 英語互換
    if ($id > $max) $max = $id;
  }
  return $max + 1;
}

// ======== 予約不可日CSV（「日付」1列フォーマット） ========
function blocked_headers() { return ['日付']; }
function ensure_blocked_exists($file) {
  ensure_data_dir();
  if (!file_exists($file)) {
    $fp = fopen($file, 'w');
    if (!$fp) err('予約不可日CSVを作成できませんでした', 500);
    fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($fp, blocked_headers()); // ヘッダは日付のみ
    fclose($fp);
    @chmod($file, 0664);
  }
}
/** 何列でも読み、キー名で取り込む（旧3列にも対応） */
function read_all_blocked($file) {
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
/** 「日付」だけで正規化＆重複削除して書き直す */
function write_all_blocked($file, $rows) {
  ensure_blocked_exists($file);
  $unique = [];
  foreach ($rows as $r) {
    $d = trim($r['日付'] ?? '');
    if ($d !== '') $unique[$d] = true;
  }
  if (($fp = fopen($file, 'w')) === false) return false;
  fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
  fputcsv($fp, blocked_headers()); // 日付のみ
  foreach (array_keys($unique) as $d) fputcsv($fp, [$d]);
  fclose($fp);
  return true;
}
/** 旧フォーマットでも呼び出し時に1列へ整える */
function normalize_blocked_format($file) {
  $rows = read_all_blocked($file);
  write_all_blocked($file, $rows);
}
/** 指定日が予約不可か（YYYY-MM-DD） */
function is_blocked_date($file, $ymd) {
  foreach (read_all_blocked($file) as $r) {
    if (trim($r['日付'] ?? '') === $ymd) return true;
  }
  return false;
}

// ===== アクション分岐 =====
$action = $_GET['action'] ?? '';

switch ($action) {

  // ---- 予約一覧 ----
  case 'list': {
    $rows = read_all_csv($GLOBALS['DATA_FILE']);

    // 日本語/英語どちらのヘッダでも拾えるように
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
        $bk = "\xEF\xBB\xBF" . $k; // BOM付きキー対策
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

  // ---- 予約作成 ----
  case 'create': {
    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) err('JSONの形式が不正です');

    $name      = trim($payload['name'] ?? '');
    $contact   = trim($payload['contact'] ?? '');
    $date      = trim($payload['date'] ?? ''); // YYYY-MM-DD
    $time      = trim($payload['time'] ?? '');
    $anonymous = $payload['anonymous'] ?? '';  // true/false or "はい/いいえ"
    $category  = trim($payload['category'] ?? '');
    $note      = trim($payload['note'] ?? '');

    // 必須チェック
    if ($name === '' || $date === '' || $time === '' || $category === '') {
      $missing = [];
      if ($name === '')     $missing[] = 'name';
      if ($date === '')     $missing[] = 'date';
      if ($time === '')     $missing[] = 'time';
      if ($category === '') $missing[] = 'category';
      err('必須項目が不足: ' . implode(', ', $missing));
    }

    // 日付形式（YYYY-MM-DD）
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      err('日付はYYYY-MM-DDで指定してください');
    }

    // === 予約不可日／当日・翌日ブロック（サーバー側ガード） ===
    normalize_blocked_format($GLOBALS['BLOCK_FILE']); // 旧ファイルでも1列へ
    if (is_blocked_date($GLOBALS['BLOCK_FILE'], $date)) {
      err('申し訳ありません。この日は予約不可です。別日をご選択ください。');
    }
    $today  = new DateTime('today');
    $target = DateTime::createFromFormat('Y-m-d', $date);
    if (!$target) err('日付の解釈に失敗しました');
    $diffDays = (int)$today->diff($target)->format('%r%a'); // 0=同日,1=翌日,2=2日後...
    if ($diffDays <= 1) {
      err('当日および翌日の予約はできません。2日後以降をご選択ください。');
    }

    // 追記
    $rows       = read_all_csv($GLOBALS['DATA_FILE']);
    $id         = next_id($rows);
    $created_at = date('Y-m-d H:i:s');
    $anonText   = (is_bool($anonymous) ? ($anonymous ? 'はい' : 'いいえ') : strval($anonymous));

    $row = [
      strval($id),
      $date,
      $time,
      $name,
      $contact,
      $anonText,
      $category,
      $note,
      $created_at,
    ];
    if (!append_csv_row($GLOBALS['DATA_FILE'], $row)) {
      err('書き込みに失敗しました', 500);
    }
    ok(['id' => $id]);
  }

  // ---- 予約削除 ----
  case 'delete': {
    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) err('JSONの形式が不正です');

    $id = intval($payload['id'] ?? 0);
    if ($id <= 0) err('IDが不正です');

    $rows     = read_all_csv($GLOBALS['DATA_FILE']);
    $found    = false;
    $filtered = [];
    foreach ($rows as $r) {
      $rid = 0;
      if (isset($r['ID'])) $rid = intval($r['ID']);
      elseif (isset($r["\xEF\xBB\xBF" . 'ID'])) $rid = intval($r["\xEF\xBB\xBF" . 'ID']);
      elseif (isset($r['id'])) $rid = intval($r['id']); // 互換
      if ($rid === $id) { $found = true; continue; }
      $filtered[] = $r;
    }
    if (!$found) err('指定のIDが見つかりませんでした', 404);

    if (!write_all_csv($GLOBALS['DATA_FILE'], $filtered)) {
      err('削除の書き込みに失敗しました', 500);
    }
    ok();
  }

  // ---- 予約不可日 一覧（「日付」だけ返す）----
  case 'blocked_list': {
    normalize_blocked_format($GLOBALS['BLOCK_FILE']);
    $rows = read_all_blocked($GLOBALS['BLOCK_FILE']);
    $out  = [];
    foreach ($rows as $r) {
      $d = trim($r['日付'] ?? '');
      if ($d !== '') $out[] = ['date' => $d];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
  }

  // ---- 予約不可日 追加（冪等）----
  case 'blocked_add': {
    $raw = file_get_contents('php://input');
    $p   = json_decode($raw, true);
    if (!is_array($p)) err('JSONの形式が不正です');

    $date = trim($p['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付はYYYY-MM-DDで指定してください');

    normalize_blocked_format($GLOBALS['BLOCK_FILE']);
    $list = read_all_blocked($GLOBALS['BLOCK_FILE']);
    foreach ($list as $r) if (trim($r['日付'] ?? '') === $date) ok(); // 既存→OK
    $list[] = ['日付' => $date];
    if (!write_all_blocked($GLOBALS['BLOCK_FILE'], $list)) err('書き込みに失敗しました', 500);
    ok();
  }

  // ---- 予約不可日 削除 ----
  case 'blocked_delete': {
    $raw = file_get_contents('php://input');
    $p   = json_decode($raw, true);
    if (!is_array($p)) err('JSONの形式が不正です');

    $date = trim($p['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('日付はYYYY-MM-DDで指定してください');

    normalize_blocked_format($GLOBALS['BLOCK_FILE']);
    $list     = read_all_blocked($GLOBALS['BLOCK_FILE']);
    $filtered = [];
    foreach ($list as $r) if (trim($r['日付'] ?? '') !== $date) $filtered[] = $r;
    if (!write_all_blocked($GLOBALS['BLOCK_FILE'], $filtered)) err('削除に失敗しました', 500);
    ok();
  }

  // ---- 未知アクション ----
  default:
    err('未知のactionです');
}
