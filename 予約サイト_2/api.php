<?php
header('Content-Type: application/json; charset=utf-8');
$DATA_DIR  = __DIR__ . '/data';
$DATA_FILE = $DATA_DIR . '/reservations.json';
if (!is_dir($DATA_DIR)) { mkdir($DATA_DIR, 0777, true); }
if (!file_exists($DATA_FILE)) { file_put_contents($DATA_FILE, "[]", LOCK_EX); }
function ok($extra = []) { echo json_encode(array_merge(['ok'=>true], $extra), JSON_UNESCAPED_UNICODE); exit; }
function err($msg, $code = 400) { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function read_all($file) {
  $fp = fopen($file, 'r'); if (!$fp) return [];
  flock($fp, LOCK_SH); $raw = stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp);
  $data = json_decode($raw, true); return is_array($data) ? $data : [];
}
function write_all($file, $data) {
  $fp = fopen($file, 'c+'); if (!$fp) return false;
  if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
  ftruncate($fp, 0); rewind($fp);
  $ok = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
  fflush($fp); flock($fp, LOCK_UN); fclose($fp); return $ok;
}
$action = $_GET['action'] ?? 'list';
switch ($action) {
  case 'list':
    $all = read_all($DATA_FILE);
    echo json_encode($all, JSON_UNESCAPED_UNICODE); exit;
  case 'create':
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name    = trim($input['name'] ?? '');
    $date    = trim($input['date'] ?? '');
    $time    = trim($input['time'] ?? '');
    $contact = trim($input['contact'] ?? '');
    $memo    = trim($input['memo'] ?? '');
    if ($name === '' || $date === '' || $time === '') err('name, date, time は必須です。');
    $all = read_all($DATA_FILE);
    $nextId = !empty($all) ? (max(array_column($all, 'id')) + 1) : 1;
    $record = ['id'=>$nextId,'name'=>$name,'date'=>$date,'time'=>$time,'contact'=>$contact,'memo'=>$memo,'createdAt'=>time()];
    $all[] = $record;
    if (!write_all($DATA_FILE, $all)) err('書き込みに失敗しました。', 500);
    ok(['id'=>$nextId]);
  case 'delete':
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($input['id'] ?? 0); if ($id <= 0) err('id が不正です。');
    $all = read_all($DATA_FILE);
    $before = count($all);
    $all = array_values(array_filter($all, fn($r)=> (int)$r['id'] !== $id));
    if ($before === count($all)) err('指定のIDが見つかりませんでした。', 404);
    if (!write_all($DATA_FILE, $all)) err('削除の書き込みに失敗しました。', 500);
    ok();
  default: err('未知のactionです。');
}