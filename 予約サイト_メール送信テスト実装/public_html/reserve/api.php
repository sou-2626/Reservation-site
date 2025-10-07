<?php
require_once __DIR__ . '/config.php';

// アクション取得
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'create':
        createReservation();
        break;
    case 'list':
        listReservations();
        break;
    case 'delete':
        deleteReservation();
        break;
    default:
        echo "Invalid action.";
        break;
}

// =============================
// 予約作成
// =============================
function createReservation() {
    $date = $_POST['date'] ?? '';
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $time = $_POST['time'] ?? '';
    $category = $_POST['category'] ?? '';
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;

    if (!$date || !$company || !$email || !$time || !$category) {
        exit("必須項目が入力されていません。");
    }

    // CSVに保存
    $fp = fopen(RESERVATIONS_CSV, 'a');
    fputcsv($fp, [
        uniqid(),   // ID
        $date,
        $company,
        $email,
        $time,
        $category,
        $anonymous,
        date("Y-m-d H:i:s")
    ]);
    fclose($fp);

    // 同時に予約不可日として保存
    $fp = fopen(BLOCKED_DATES_CSV, 'a');
    fputcsv($fp, [$date]);
    fclose($fp);

    // 通知メール送信
    $subject = "新しい予約が入りました ($date)";
    $body = "以下の内容で予約がありました：\n\n".
            "企業名: $company\n".
            "メール: $email\n".
            "日付: $date\n".
            "時間帯: $time\n".
            "カテゴリ: $category\n".
            "匿名表示: ".($anonymous ? "はい" : "いいえ")."\n";

    // 管理者宛
    foreach (getAdminEmails() as $adminEmail) {
        sendMail($adminEmail, $subject, $body);
    }

    // ユーザー宛
    $userBody = "ご予約ありがとうございます。\n以下の内容で受け付けました。\n\n".
                "日付: $date\n".
                "時間帯: $time\n".
                "カテゴリ: $category\n\n".
                "※予約当日の前日にリマインドメールを送信します。";
    sendMail($email, "予約確認のお知らせ", $userBody);

    header("Location: calendar.php");
    exit;
}

// =============================
// 予約一覧を返す（管理者用）
// =============================
function listReservations() {
    $data = [];
    if (file_exists(RESERVATIONS_CSV)) {
        $fp = fopen(RESERVATIONS_CSV, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            $data[] = $row;
        }
        fclose($fp);
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
}

// =============================
// 予約削除（管理者用）
// =============================
function deleteReservation() {
    $id = $_POST['id'] ?? '';
    if (!$id) {
        exit("IDが指定されていません。");
    }

    $rows = [];
    if (file_exists(RESERVATIONS_CSV)) {
        $fp = fopen(RESERVATIONS_CSV, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            if ($row[0] !== $id) {
                $rows[] = $row;
            }
        }
        fclose($fp);
    }

    // 書き戻し
    $fp = fopen(RESERVATIONS_CSV, 'w');
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    // 予約不可日CSVも更新（該当日が他の予約に無ければ解除）
    $dates = array_column($rows, 1);
    $blocked = [];
    if (file_exists(BLOCKED_DATES_CSV)) {
        $fp = fopen(BLOCKED_DATES_CSV, 'r');
        while (($row = fgetcsv($fp)) !== false) {
            if (in_array($row[0], $dates)) {
                $blocked[] = $row;
            }
        }
        fclose($fp);
    }
    $fp = fopen(BLOCKED_DATES_CSV, 'w');
    foreach ($blocked as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    header("Location: admin_reservations.php");
    exit;
}
