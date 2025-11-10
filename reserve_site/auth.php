<?php
// auth.php - SQLite対応（相対パス・初期化付き）
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tokyo');

try {
    // reserve_site/data/reservation_system.db に統一
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/reservation_system.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 初期化（存在しなければ作成）
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS accounts (
        role TEXT PRIMARY KEY,
        id TEXT NOT NULL,
        password TEXT NOT NULL
    );
    ");

    // 初期レコードを1回だけ投入
    if ((int)$pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO accounts (role, id, password)
            VALUES ('admin','admin','adminpass'),
                   ('user','user','userpass');
        ");
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'DB接続エラー: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// ✅ ここを追加（Ping応答）
if ($action === 'ping') {
    echo json_encode(['ok' => true]);
    exit;
}

switch ($action) {
    // 管理画面・ログイン前のID確認
    case 'get_ids':
        $res = $pdo->query("SELECT role, id FROM accounts");
        $out = ['admin' => [], 'user' => []];
        foreach ($res as $row) {
            $out[$row['role']] = ['id' => $row['id']];
        }
        echo json_encode($out);
        break;

    // ログイン認証
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $role = $input['role'] ?? '';
        $pass = $input['password'] ?? '';
        $stmt = $pdo->prepare("SELECT password FROM accounts WHERE role = ?");
        $stmt->execute([$role]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $pass === $row['password']) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'IDまたはパスワードが正しくありません']);
        }
        break;

    // アカウント情報更新（IDまたはパスワード）
    case 'update_account':
        $input = json_decode(file_get_contents('php://input'), true);
        $role = $input['role'] ?? '';
        $id = $input['id'] ?? null;
        $pass = $input['password'] ?? null;
        if (!$role) {
            echo json_encode(['ok' => false, 'error' => 'ロールが指定されていません']);
            break;
        }

        $fields = [];
        $params = [];
        if ($id) {
            $fields[] = "id = ?";
            $params[] = $id;
        }
        if ($pass) {
            $fields[] = "password = ?";
            $params[] = $pass;
        }
        if (empty($fields)) {
            echo json_encode(['ok' => false, 'error' => '更新項目がありません']);
            break;
        }

        $params[] = $role;
        $sql = "UPDATE accounts SET " . implode(", ", $fields) . " WHERE role = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['error' => '未対応のアクションです']);
}
?>
