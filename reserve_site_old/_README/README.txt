初期ID
ユーザーログイン
ID: user
pass: userpass

管理者ログイン
ID: admin
pass: adminpass

※初回ログイン後は、管理画面からパスワードを変更してください。

---

【アップロード方法】

FFFTPなどで、サーバーの公開ディレクトリ（例：public_html / www）内に
「reserve_site」フォルダごとアップロードしてください。

【ロリポップでの設定例（参考）】

1: サーバーの管理・設定 → cron設定
2: 設定名 → わかりやすいもの（例：予約の前日リマインド）
3: 日付は「毎月・毎日・毎曜日」すべてチェック
4: 時間は通知したい時間（例: 8時30分）
5: 実行ファイル → reserve_site/send_reminder.php
6: 一番下の設定ボタンを押す

「処理は正常に終了しました。」と表示されれば完了です。

---

【メール送信のサーバー依存設定】
send_reminderの中にある
送信元メール（From）を自分のドメインに合わせて設定してください。
   例）
   $from = 'no-reply@「your-domain」.example';
   $headers = "From: {$from}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n";
