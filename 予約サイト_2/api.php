<?php
/**
 * 予約API（JSONファイル保存）
 * - list   : 予約一覧を返す（GET /api.php?action=list）
 * - create : 予約を1件追加（POST JSON /api.php?action=create）
 * - delete : 指定IDを削除（POST JSON /api.php?action=delete）
 *
 * 保存先:
 *   ./data/reservations.json  （存在しなければ自動作成）
 *
 * メモ:
 * - ファイルI/Oは flock で排他制御（同時アクセスでも壊れにくい）
 * - JSONは UTF-8 / 日本語そのまま（JSON_UNESCAPED_UNICODE）
 * - 本番では data/ に書き込み権限が必要（例: 775、ファイルは 664）
 */

header('Content-Type: application/json; charset=utf-8');

// 保存先パス（スクリプトのあるディレクトリ基準で安全に解決）
$DATA_DIR  = __DIR__ . '/data';
$DATA_FILE = $DATA_DIR . '/reservations.json';

// 初回起動時のディレクトリ・ファイルを用意
if (!is_dir($DATA_DIR)) {
  // 第3引数 true で親ディレクトリが無くても再帰的に作成
  mkdir($DATA_DIR, 0777, true);
}
if (!file_exists($DATA_FILE)) {
  // 初期データは空配列。LOCK_EX で同時書き込みを抑止
  file_put_contents($DATA_FILE, "[]", LOCK_EX);
}

/**
 * 成功レスポンスを返して終了
 * @param array $extra 追加で返したいキー（例: ['id'=>1]）
 */
function ok($extra = []) {
  echo json_encode(array_merge(['ok'=>true], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * エラーレスポンスを返して終了（HTTPステータス付き）
 * @param string $msg  エラーメッセージ
 * @param int    $code HTTPステータス（既定 400）
 */
function err($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * JSON全件を読み出す（共有ロック）
 * @return array 予約配列（失敗時は空配列）
 */
function read_all($file) {
  $fp = fopen($file, 'r');
  if (!$fp) return [];
  // 共有ロックで読み取り（他プロセスの書き込みは待たせる）
  flock($fp, LOCK_SH);
  $raw = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);

  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

/**
 * JSON全件を書き戻す（排他ロック）
 * @return bool 成否
 */
function write_all($file, $data) {
  // 'c+'：存在しなければ作成、読み書き可
  $fp = fopen($file, 'c+');
  if (!$fp) return false;

  // 排他ロック（書き込み中は他の読み書きをブロック）
  if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }

  // 既存内容をクリアして先頭から書く
  ftruncate($fp, 0);
  rewind($fp);

  // 読みやすさのため PRETTY_PRINT、文字化け回避のため UNESCAPED_UNICODE
  $ok = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;

  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $ok;
}

// action を振り分け（既定は list）
$action = $_GET['action'] ?? 'list';

switch ($action) {
  case 'list': {
    // 予約一覧をそのまま返す
    $all = read_all($DATA_FILE);
    echo json_encode($all, JSON_UNESCAPED_UNICODE);
    exit;
  }

  case 'create': {
    // JSON本文をパース（空なら空配列に）
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    // 必須項目を取り出し＆トリム
    $name    = trim($input['name']    ?? '');
    $date    = trim($input['date']    ?? ''); // 期待値: 'YYYY-MM-DD'
    $time    = trim($input['time']    ?? ''); // 例: '午前' / '午後' / 'HH:MM'
    $contact = trim($input['contact'] ?? '');
    $memo    = trim($input['memo']    ?? '');

    // バリデーション（最小限）
    if ($name === '' || $date === '' || $time === '') {
      err('name, date, time は必須です。'); // 400 Bad Request
    }

    // 既存データ読み出し → 連番IDを採番
    $all = read_all($DATA_FILE);
    $nextId = !empty($all) ? (max(array_column($all, 'id')) + 1) : 1;

    // レコード作成
    $record = [
      'id'        => $nextId,
      'name'      => $name,
      'date'      => $date,
      'time'      => $time,
      'contact'   => $contact,
      'memo'      => $memo,
      'createdAt' => time(), // UNIX秒
    ];

    // 配列末尾に追加して書き戻し
    $all[] = $record;
    if (!write_all($DATA_FILE, $all)) {
      err('書き込みに失敗しました。', 500); // 500 Internal Server Error
    }

    ok(['id' => $nextId]); // 例: { ok:true, id: 3 }
  }

  case 'delete': {
    // JSON本文から id を取得
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) err('id が不正です。');

    // 対象以外を残す（=対象IDを削除）
    $all = read_all($DATA_FILE);
    $before = count($all);
    $all = array_values(array_filter($all, fn($r) => (int)$r['id'] !== $id));

    if ($before === count($all)) {
      // 1件も減っていない＝該当ID無し
      err('指定のIDが見つかりませんでした。', 404);
    }

    if (!write_all($DATA_FILE, $all)) {
      err('削除の書き込みに失敗しました。', 500);
    }

    ok(); // { ok:true }
  }

  default:
    // 未知のアクション
    err('未知のactionです。');
}
