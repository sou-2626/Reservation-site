<?php
// auth.php  平文でID/パスを data/auth.json に保存する最小構成
// エンドポイント：
// - POST  ?action=login           {role, password}
// - GET   ?action=get_ids         → {admin:{id}, user:{id}}
// - POST  ?action=change_password {role, old_password(任意), new_password}
// - POST  ?action=update_account  {role, id(任意), password(任意)}  ※直接上書き

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"PHPエラー: $errstr ($errfile:$errline)"], JSON_UNESCAPED_UNICODE);
  exit;
});

$AUTH_FILE = __DIR__ . '/data/auth.json';

function jserr($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function jsok($data=null){ echo json_encode($data ?? ['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }

function ensure_auth_file($file){
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
  if (!file_exists($file)) {
    $init = [
      'admin' => ['id' => 'admin', 'password' => 'admin123'],
      'user'  => ['id' => 'user',  'password' => 'user123']
    ];
    file_put_contents($file, json_encode($init, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    @chmod($file, 0664);
  }
}

function read_auth($file){
  ensure_auth_file($file);
  $json = @file_get_contents($file);
  $data = json_decode($json, true);
  if (!is_array($data)) $data = [];
  // 欠損補完
  foreach (['admin','user'] as $r){
    if (!isset($data[$r]) || !is_array($data[$r])) $data[$r] = [];
    if (!isset($data[$r]['id'])) $data[$r]['id'] = $r;
    if (!isset($data[$r]['password'])) $data[$r]['password'] = $r.'123';
  }
  return $data;
}

function write_auth($file, $data){
  $tmp = $file . '.tmp';
  $fp = fopen($tmp, 'w');
  if (!$fp) return false;
  fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  fclose($fp);
  return @rename($tmp, $file);
}

$action = $_GET['action'] ?? '';
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

switch ($action) {
  case 'login': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    $pw   = (string)($payload['password'] ?? '');
    if ($role === '' || $pw === '') jserr('role と password は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr('指定のロールが存在しません', 404);
    if (($auth[$role]['password'] ?? '') !== $pw) jserr('パスワードが違います', 401);

    jsok(['ok'=>true]);
  }

  case 'get_ids': {
    $auth = read_auth($AUTH_FILE);
    jsok([
      'admin' => ['id' => $auth['admin']['id'] ?? 'admin'],
      'user'  => ['id' => $auth['user']['id']  ?? 'user' ],
    ]);
  }

  case 'change_password': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    $old  = isset($payload['old_password']) ? (string)$payload['old_password'] : null; // 任意
    $new  = (string)($payload['new_password'] ?? '');
    if ($role === '' || $new === '') jserr('role / new_password は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr('指定のロールが存在しません', 404);
    // old_password 指定があれば一致チェック
    if ($old !== null && ($auth[$role]['password'] ?? '') !== $old) jserr('現在のパスワードが違います', 401);

    $auth[$role]['password'] = $new;
    if (!write_auth($AUTH_FILE, $auth)) jserr('書き込みに失敗しました', 500);
    jsok();
  }

  case 'update_account': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    if ($role === '') jserr('role は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr('指定のロールが存在しません', 404);

    // 渡されたものだけ上書き（ID/パスどちらも任意）
    if (isset($payload['id']))       $auth[$role]['id'] = (string)$payload['id'];
    if (isset($payload['password'])) $auth[$role]['password'] = (string)$payload['password'];

    if (!write_auth($AUTH_FILE, $auth)) jserr('書き込みに失敗しました', 500);
    jsok();
  }

  default:
    jserr('未知のactionです');
}
