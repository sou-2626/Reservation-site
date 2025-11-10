<?php
// api.php - SQLite対応（完全版・相対パス・calendar.js対応・UTF-8メール対応・カテゴリ「両方」対応）
header('Content-Type: application/json');
date_default_timezone_set('Asia/Tokyo');

try {
    // reserve_site/data/reservation_system.db に保存されている前提
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/reservation_system.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 高速化設定
    $pdo->exec("PRAGMA journal_mode=WAL;");
    $pdo->exec("PRAGMA synchronous=NORMAL;");

    // === 初回DB構築 ===
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS reservations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT, contact TEXT,
        date TEXT, time TEXT,
        anonymous TEXT, category TEXT, note TEXT
    );
    CREATE TABLE IF NOT EXISTS blocked_dates (
        date TEXT PRIMARY KEY
    );
    CREATE TABLE IF NOT EXISTS admin_mail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT, email TEXT UNIQUE
    );
    CREATE TABLE IF NOT EXISTS accounts (
        role TEXT PRIMARY KEY,
        id TEXT NOT NULL,
        password TEXT NOT NULL
    );
    ");

    // accounts初期化（存在しない場合のみ）
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

// action取得
$action = $_GET['action'] ?? '';

switch ($action) {

    // --- 保存（calendar.jsなどから） ---
    case 'create':
    case 'save':
    case 'save_reservation':
    case 'reservation_add':
    case 'add_reservation':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['ok' => false, 'error' => '入力が不正です']);
            break;
        }

        // ✅ カテゴリ名変換（両方対応）
        $category = $input['category'] ?? '';
        if ($category === 'both' || $category === '両方') {
            $category = 'ゲーム, CG';
        } elseif ($category === 'game' || $category === 'ゲーム') {
            $category = 'ゲーム';
        } elseif ($category === 'cg' || $category === 'ＣＧ' || $category === 'CG') {
            $category = 'CG';
        }

        // DBへ保存
        $stmt = $pdo->prepare("
            INSERT INTO reservations (name, contact, date, time, anonymous, category, note)
            VALUES (:name, :contact, :date, :time, :anonymous, :category, :note)
        ");
        $stmt->execute([
            ':name' => $input['name'] ?? '',
            ':contact' => $input['contact'] ?? '',
            ':date' => $input['date'] ?? '',
            ':time' => $input['time'] ?? '',
            ':anonymous' => !empty($input['anonymous']) ? 'はい' : 'いいえ',
            ':category' => $category,
            ':note' => $input['note'] ?? ''
        ]);

        // ==========================
        // 💌 メール送信（UTF-8対応）
        // ==========================
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");

        // 送信元を自動判定
        $domain = $_SERVER['SERVER_NAME'] ?? '';
        if (strpos($domain, 'flat-amami-7790.fool.jp') !== false) {
            $from = 'no-reply@flat-amami-7790.fool.jp';
        } elseif (!empty($domain)) {
            $from = "no-reply@{$domain}";
        } else {
            $from = 'no-reply@localhost';
        }

        $headers = "From: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";

        // --- 予約者へのメール ---
        $userEmail = $input['contact'] ?? '';
        if (filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = "【予約完了】ご予約を受け付けました";
            $message = "この度はご予約ありがとうございます。\n\n"
                     . "以下の内容でご予約を受け付けました。\n\n"
                     . "企業名：{$input['name']}\n"
                     . "日付：{$input['date']}\n"
                     . "時間：{$input['time']}\n"
                     . "カテゴリ：{$category}\n"
                     . "備考：{$input['note']}\n\n"
                     . "このメールは自動送信です。";
            mb_send_mail($userEmail, $subject, $message, $headers);
        }

        // --- 管理者へのメール ---
        $adminList = $pdo->query("SELECT email FROM admin_mail")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminList as $adminEmail) {
            if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $subject = "【予約通知】新しい予約が入りました";
                $message = "以下の内容で新しい予約が登録されました。\n\n"
                         . "企業名：{$input['name']}\n"
                         . "日付：{$input['date']}\n"
                         . "時間：{$input['time']}\n"
                         . "カテゴリ：{$category}\n"
                         . "備考：{$input['note']}\n\n"
                         . "管理画面で内容をご確認ください。";
                mb_send_mail($adminEmail, $subject, $message, $headers);
            }
        }

        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        break;


    // --- 一覧取得 ---
    case 'reservation_list':
    case 'list':
        $stmt = $pdo->query("SELECT * FROM reservations ORDER BY date, time");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // --- 削除 ---
    case 'reservation_delete':
    case 'delete_reservation':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'idがありません']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    // --- 更新 ---
    case 'reservation_update':
    case 'update_reservation':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'idがありません']);
            break;
        }
        $stmt = $pdo->prepare("
            UPDATE reservations SET date=?, time=?, contact=?, category=?, note=? WHERE id=?
        ");
        $stmt->execute([
            $input['date'] ?? '',
            $input['time'] ?? '',
            $input['contact'] ?? '',
            $input['category'] ?? '',
            $input['note'] ?? '',
            $id
        ]);
        echo json_encode(['ok' => true]);
        break;

    // --- 予約不可日 ---
    case 'blocked_list':
        $res = $pdo->query("SELECT date FROM blocked_dates");
        echo json_encode(array_column($res->fetchAll(PDO::FETCH_ASSOC), 'date'));
        break;

    case 'blocked_add':
        $input = json_decode(file_get_contents('php://input'), true);
        $date = $input['date'] ?? '';
        if (!$date) {
            echo json_encode(['ok' => false, 'error' => 'dateがありません']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO blocked_dates (date) VALUES (?)");
        $stmt->execute([$date]);
        echo json_encode(['ok' => true]);
        break;

    case 'blocked_delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $date = $input['date'] ?? '';
        if (!$date) {
            echo json_encode(['ok' => false, 'error' => 'dateがありません']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM blocked_dates WHERE date = ?");
        $stmt->execute([$date]);
        echo json_encode(['ok' => true]);
        break;

    // --- 管理者メール ---
    case 'mail_list':
        $stmt = $pdo->query("SELECT name, email FROM admin_mail ORDER BY name");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'mail_add':
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';
        if (!$name || !$email) {
            echo json_encode(['ok' => false, 'error' => 'name/emailがありません']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO admin_mail (name, email) VALUES (?, ?)");
        $stmt->execute([$name, $email]);
        echo json_encode(['ok' => true, 'message' => '登録しました']);
        break;

    case 'mail_delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        if (!$email) {
            echo json_encode(['ok' => false, 'error' => 'emailがありません']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM admin_mail WHERE email = ?");
        $stmt->execute([$email]);
        echo json_encode(['ok' => true, 'message' => '削除しました']);
        break;

    // --- デフォルト ---
    default:
        echo json_encode(['error' => "未対応のアクションです: {$action}"]);
}
?>
