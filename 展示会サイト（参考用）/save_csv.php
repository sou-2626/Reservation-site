<?php
$data = json_decode(file_get_contents('php://input'), true);

$file = $data['file'];
$rows = $data['data'];

if (!$file || !is_array($rows)) {
    echo json_encode(['success' => false, 'message' => '無効なデータ']);
    exit;
}

// ファイルをバイナリモードで開く
$fp = fopen($file, 'wb');
if (!$fp) {
    echo json_encode(['success' => false, 'message' => 'ファイルが開けません']);
    exit;
}

// UTF-8 BOM を付与（必要に応じて）
fwrite($fp, "\xEF\xBB\xBF");

foreach ($rows as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

echo json_encode(['success' => true]);
?>
