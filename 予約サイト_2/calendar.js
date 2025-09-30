// calendar.js v3.8（single file, local-date safe）
// 目的：予約カレンダーの「表示・選択・送信」を一つのファイルで完結。
// 方針：日付は常に“ローカル日付”で扱い、APIとのやり取りは "YYYY-MM-DD" の文字列で統一。

// ==============================
// 認証（未ログイン→ログイン画面へ遷移）
// ==============================
if (!sessionStorage.getItem('loggedIn')) {
  window.location.href = "login.html";
}
// 管理者ログインへの遷移（ボタンから呼ばれる想定）
function goToAdminLogin() { window.location.href = "admin_login.html"; }

// ==============================
// 予約可否ポリシー（画面の制約）
// - UI側の制御であり、サーバー側の最終チェックは別途必要
// - 運用に応じて true/false を切替
// ==============================
const POLICY = {
  blockWeekend: true,            // 土日（0:日, 6:土）を予約不可
  blockPastTodayTomorrow: true,  // 過去/当日/翌日を予約不可
  useServerBlockedDates: true    // 管理画面で登録された予約不可日を反映
};

// ==============================
// 日付ユーティリティ（ローカル日付だけを使う）
// ・"YYYY-MM-DD" ⇔ "YYYY年M月D日" 変換
// ・Date生成は new Date(y, m0, d) を使用（文字列のDateパースはしない）
// ・APIへ渡す/受け取るのは常に "YYYY-MM-DD"（ゼロ埋め）
// ==============================
function pad2(n){ return String(n).padStart(2,'0'); }

/** "YYYY年M月D日" → "YYYY-MM-DD" */
function jpDateToISO(jp){
  const m = (jp || "").match(/^(\d{4})年(\d{1,2})月(\d{1,2})日$/);
  if (!m) return "";
  const [_, y, mo, d] = m;
  return `${y}-${pad2(+mo)}-${pad2(+d)}`;
}

/** "YYYY-MM-DD" → "YYYY年M月D日" */
function isoToJP(iso){
  const m = (iso || "").match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!m) return iso || "";
  const [_, y, mo, d] = m;
  return `${y}年${+mo}月${+d}日`;
}

/** y, m0(0-11), d → "YYYY-MM-DD" */
function ymdISO(year, month0, day){
  return `${year}-${pad2(month0+1)}-${pad2(day)}`;
}

/** "2025-1-2" / "2025/1/2" / "20250102" などを "YYYY-MM-DD" に正規化 */
function normalizeISO(s){
  if (typeof s !== 'string') return '';
  const t = s.trim();
  let m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (m) return `${m[1]}-${pad2(+m[2])}-${pad2(+m[3])}`;
  m = t.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
  if (m) return `${m[1]}-${pad2(+m[2])}-${pad2(+m[3])}`;
  m = t.match(/^(\d{4})(\d{2})(\d{2})$/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  return '';
}

// ==============================
// クライアント状態（画面メモリ）
// ・reservations: /list の取得結果を画面表示用に保持（dateはJP表記）
// ・blockedDates: /blocked_list の結果を "YYYY-MM-DD" 配列で保持
// ==============================
let reservations = []; // {date(JP), time, company, anonymous("はい"/"いいえ"), category, note}
let blockedDates = []; // ["YYYY-MM-DD", ...]

// ==============================
// API（キャッシュ無効化）
// ・?ts=UNIXTIME と cache:'no-store' でブラウザキャッシュ回避
// ・戻り値は常に res.json() を返却（エラーハンドリングは呼び出し側で）
// ==============================
async function apiCreateReservation(payload){
  const res = await fetch('./api.php?action=create&ts=' + Date.now(), {
    method: 'POST',
    headers: {'Content-Type':'application/json','Cache-Control':'no-store'},
    cache: 'no-store',
    body: JSON.stringify(payload)
  });
  return res.json();
}
async function apiListReservations(){
  const res = await fetch('./api.php?action=list&ts=' + Date.now(), {
    headers: {'Cache-Control':'no-store'},
    cache: 'no-store'
  });
  return res.json();
}
async function apiListBlockedRaw(){
  const res = await fetch('./api.php?action=blocked_list&ts=' + Date.now(), {
    headers: {'Cache-Control':'no-store'},
    cache: 'no-store'
  });
  return res.json();
}

/** blocked_list の戻りが "文字列配列" / "オブジェクト配列" どちらでもISO配列に正規化 */
function toISOBlockedArray(raw){
  const out = [];
  if (Array.isArray(raw)) {
    for (const item of raw) {
      if (typeof item === 'string') {
        const n = normalizeISO(item);
        if (n) out.push(n);
      } else if (item && typeof item === 'object') {
        const candidate = item.date ?? item['日付'] ?? item.Date ?? item.DATE ?? '';
        const n = normalizeISO(String(candidate || ''));
        if (n) out.push(n);
      }
    }
  }
  // 重複除去（Set）して配列へ
  return Array.from(new Set(out));
}

// ==============================
// 予約可否判定
// ・既存予約/管理側不可日/当日まで（＋翌日）/週末を一括で判定
// ・UIでは reserved クラス＋title 付与のみ（見た目はCSS任せ）
// ==============================
function checkReservability({ dateObj, isoDate, hasAnyReservation, todayBase, serverBlockedISO }){
  // 1) 既に予約がある
  if (hasAnyReservation) return { blocked: true, reason: "既に予約があります" };
  // 2) 管理側で不可日に指定
  if (POLICY.useServerBlockedDates && serverBlockedISO.includes(isoDate)) {
    return { blocked: true, reason: "予約不可日（管理者設定）" };
  }
  // 3) 過去/当日/翌日（＝明日の0:00までを不許可）
  if (POLICY.blockPastTodayTomorrow) {
    const tomorrow = new Date(todayBase);
    tomorrow.setDate(todayBase.getDate() + 1);
    if (dateObj <= tomorrow) return { blocked: true, reason: "過去・当日・翌日は予約できません" };
  }
  // 4) 土日
  if (POLICY.blockWeekend) {
    const dow = dateObj.getDay(); // 0:日,6:土
    if (dow === 0 || dow === 6) return { blocked: true, reason: "土日は予約できません" };
  }
  // 5) 上記に該当しなければ予約可能
  return { blocked: false, reason: "" };
}

// ==============================
// サーバー→画面メモリ同期
// ・/list をJP日付へ変換して reservations に格納（描画時の比較を簡単に）
// ・/blocked_list をISO配列に正規化して blockedDates に格納
// ==============================
async function syncLocalFromApi(){
  try {
    const list = await apiListReservations();
    reservations = Array.isArray(list) ? list.map(r => ({
      date: isoToJP(r.date),                      // "YYYY年M月D日" にしておく
      time: r.time || "",
      company: r.name || "",
      anonymous:
        (r.anonymous === true || r.anonymous === "はい") ? "はい" :
        (r.anonymous === "いいえ" ? "いいえ" : (r.anonymous || "いいえ")),
      category: r.category || "",
      note: r.note || ""
    })) : [];

    const rawBlocks = await apiListBlockedRaw();
    blockedDates = toISOBlockedArray(rawBlocks);
  } catch (e) {
    console.error('syncLocalFromApi failed', e);
    reservations = [];
    blockedDates = [];
  }
}

// ==============================
// 初期化＆描画
// ・月送り/予約選択/送信のイベントもここで定義
// ==============================
window.addEventListener('DOMContentLoaded', () => {
  // --- 要素取得（存在前提のID） ---
  const calendar = document.getElementById('calendar');                 // 日付グリッドの親
  const monthLabel = document.getElementById('current-month');          // ヘッダの「YYYY年 M月」
  const prevBtn = document.getElementById('prev-month');                // 前月へ
  const nextBtn = document.getElementById('next-month');                // 次月へ
  const selectedDateDisplay = document.getElementById('selected-date'); // 選択状態の表示
  const reservationForm = document.getElementById('reservation-form');  // 入力フォームのラッパ
  const form = document.getElementById('form');                         // 予約フォームそのもの

  // --- 今日の0:00を基準にして扱う（時間部分は使わない） ---
  const today = new Date(); today.setHours(0,0,0,0);
  const todayYear = today.getFullYear();
  const todayMonth = today.getMonth();

  // --- 画面状態（現在表示している年月、選択中の日） ---
  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null; // "YYYY年M月D日"（JP表記）

  /** カレンダーを指定年月で再描画 */
  function renderCalendar(year, month){
    // ヘッダ更新と「過去へ戻る」制御
    monthLabel.textContent = `${year}年 ${month + 1}月`;
    prevBtn.disabled = (year < todayYear) || (year === todayYear && month <= todayMonth);

    // グリッド初期化
    calendar.innerHTML = "";

    // その月の1日と末日、先頭の曜日を計算
    const firstDay = new Date(year, month, 1);
    const lastDay  = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay(); // 0(日)～6(土)

    // 1日の前に空セルを挿入（曜日合わせ）
    for (let i = 0; i < startDay; i++) {
      const empty = document.createElement('div');
      calendar.appendChild(empty);
    }

    // 1日から末日までセル生成
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const dayEl = document.createElement('div');
      dayEl.className = 'day';

      // セルの日付（ローカル0:00）
      const dateObj = new Date(year, month, d);
      dateObj.setHours(0,0,0,0);

      // 比較で使う2形式
      const isoDate = ymdISO(year, month, d);           // "YYYY-MM-DD"（サーバー不可日との一致判定に使用）
      const jpDate  = `${year}年${month + 1}月${d}日`; // JP表記（予約一覧との一致判定に使用）

      // 日付ラベル（数字）
      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      dayEl.appendChild(dateLabel);

      // その日の予約（JP表記で一致判定）
      const dayReservations   = reservations.filter(r => r.date === jpDate);
      const hasAnyReservation = dayReservations.length > 0;

      // 予約バッジ（カテゴリ色分けは既存仕様に合わせる）
      dayReservations.forEach(r => {
        const fullName = r.anonymous === "はい" ? "匿名" : (r.company || "");
        const label    = r.time ? `${r.time} ${fullName}` : fullName;
        const badge    = document.createElement('div');
        badge.className = 'company-name';
        if (r.category === "CG")        badge.style.color = "green";
        else if (r.category === "ゲーム") badge.style.color = "blue";
        else if (r.category === "両方")   badge.style.color = "red";
        badge.textContent = label;
        if (r.time) badge.title = `${r.time} に予約`;
        dayEl.appendChild(badge);
      });

      // 予約可否の総合判定
      const { blocked, reason } = checkReservability({
        dateObj,
        isoDate,
        hasAnyReservation,
        todayBase: today,
        serverBlockedISO: blockedDates
      });

      // 不可：クラス付与＋title（ツールチップ）
      if (blocked) {
        dayEl.classList.add('reserved');
        dayEl.title = reason;
      }
      // 可能：クリックで選択→フォーム表示
      else {
        dayEl.addEventListener('click', () => {
          // 他の選択状態を解除
          calendar.querySelectorAll('.day').forEach(el => el.classList.remove('selected'));
          dayEl.classList.add('selected');

          // 選択表示とフォーム表示
          selectedDateDisplay.textContent = `${jpDate} を選択しました`;
          selectedDay = jpDate;
          reservationForm.style.display = 'block';
        });
      }

      calendar.appendChild(dayEl);
    }
  }

  // --- 月移動ボタン ---
  prevBtn.addEventListener('click', () => {
    if (currentMonth > 0) currentMonth--;
    else { currentMonth = 11; currentYear--; }
    renderCalendar(currentYear, currentMonth);
  });
  nextBtn.addEventListener('click', () => {
    if (currentMonth < 11) currentMonth++;
    else { currentMonth = 0; currentYear++; }
    renderCalendar(currentYear, currentMonth);
  });

  // --- 初期同期→初回描画 ---
  syncLocalFromApi().then(() => renderCalendar(currentYear, currentMonth));

  // --- 予約フォーム送信 ---
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // 必須入力チェック（選択日も含む）
    const company   = document.getElementById('company').value.trim();
    const time      = document.getElementById('time').value;
    const anonymous = document.getElementById('anonymous').checked;
    const category  = document.getElementById('category').value;
    const note      = document.getElementById('note').value.trim();
    if (!company || !time || !category || !selectedDay) {
      alert('企業名・時間・カテゴリ・日付は必須です');
      return;
    }

    // API用のペイロードを作成（dateは "YYYY-MM-DD"）
    const payload = {
      name: company,
      contact: '',
      date: jpDateToISO(selectedDay),
      time,
      anonymous,
      category,
      note
    };

    try {
      // 登録
      const result = await apiCreateReservation(payload);
      if (!(result && (result.ok === true || typeof result.id !== 'undefined'))) {
        alert('サーバー保存に失敗: ' + (result?.error || '不明なエラー'));
        return;
      }

      // 成功：最新状態へ同期→再描画→フォーム初期化
      await syncLocalFromApi();
      alert(`予約を受け付けました！\n${selectedDay} ${time}`);
      form.reset();
      reservationForm.style.display = 'none';
      renderCalendar(currentYear, currentMonth);
    } catch (err) {
      console.error(err);
      alert('通信エラーで保存できませんでした');
    }
  });
});
