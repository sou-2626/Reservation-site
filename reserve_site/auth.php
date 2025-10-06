<?php
// auth.php
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

$AUTH_FILE = __DIR__ . '/data/auth.json';

function jserr_soft($msg) {
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function jserr($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function jsok($data = null) {
  echo json_encode($data ?? ['ok' => true], JSON_UNESCAPED_UNICODE);
  exit;
}

function ensure_auth_file($file) {
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
  if (!file_exists($file)) {
    $init = [
      'admin' => ['id' => 'admin', 'password' => 'admin123'],
      'user'  => ['id' => 'user',  'password' => 'user123']
    ];
    file_put_contents($file, json_encode($init, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }
}

function read_auth($file) {
  ensure_auth_file($file);
  $data = json_decode(@file_get_contents($file), true);
  if (!is_array($data)) $data = [];
  foreach (['admin', 'user'] as $r) {
    if (!isset($data[$r])) $data[$r] = ['id' => $r, 'password' => $r.'123'];
  }
  return $data;
}

function write_auth($file, $data) {
  file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? '';
$payload = json_decode(file_get_contents('php://input'), true);

switch ($action) {
  case 'login': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    $pw   = (string)($payload['password'] ?? '');
    if ($role === '' || $pw === '') jserr_soft('role と password は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr_soft('指定のロールが存在しません');
    if (($auth[$role]['password'] ?? '') !== $pw) {
      // ← ここを「通信エラーではなくJSONレスポンス」に変更
      jserr_soft('ユーザーIDまたはパスワードが違います');
    }

    jsok(['ok' => true]);
    break;
  }

  case 'get_ids': {
    $auth = read_auth($AUTH_FILE);
    jsok([
      'admin' => ['id' => $auth['admin']['id'] ?? 'admin'],
      'user'  => ['id' => $auth['user']['id'] ?? 'user']
    ]);
    break;
  }

  case 'change_password': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    $new  = (string)($payload['new_password'] ?? '');
    if ($role === '' || $new === '') jserr_soft('role / new_password は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr_soft('指定のロールが存在しません');

    $auth[$role]['password'] = $new;
    write_auth($AUTH_FILE, $auth);
    jsok();
    break;
  }

  case 'update_account': {
    if (!is_array($payload)) jserr('JSONの形式が不正です');
    $role = trim($payload['role'] ?? '');
    if ($role === '') jserr_soft('role は必須です');

    $auth = read_auth($AUTH_FILE);
    if (!isset($auth[$role])) jserr_soft('指定のロールが存在しません');

    if (isset($payload['id']))       $auth[$role]['id'] = (string)$payload['id'];
    if (isset($payload['password'])) $auth[$role]['password'] = (string)$payload['password'];

    write_auth($AUTH_FILE, $auth);
    jsok();
    break;
  }

  case 'ping': jsok(['ok' => true, 'name' => 'auth.php']); break;

  default: jserr_soft('未知のactionです');
}
