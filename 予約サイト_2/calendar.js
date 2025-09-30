// calendar.js v3.8.1（today枠復活）

// ==============================
// 認証（未ログイン→ログイン画面へ遷移）
// ==============================
if (!sessionStorage.getItem('loggedIn')) {
  window.location.href = "login.html";
}
function goToAdminLogin() { window.location.href = "admin_login.html"; }

// ==============================
// 予約可否ポリシー
// ==============================
const POLICY = {
  blockWeekend: true,
  blockPastTodayTomorrow: true,
  useServerBlockedDates: true
};

// ==============================
// 日付ユーティリティ
// ==============================
function pad2(n){ return String(n).padStart(2,'0'); }
function jpDateToISO(jp){
  const m = (jp || "").match(/^(\d{4})年(\d{1,2})月(\d{1,2})日$/);
  if (!m) return "";
  const [_, y, mo, d] = m;
  return `${y}-${pad2(+mo)}-${pad2(+d)}`;
}
function isoToJP(iso){
  const m = (iso || "").match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!m) return iso || "";
  const [_, y, mo, d] = m;
  return `${y}年${+mo}月${+d}日`;
}
function ymdISO(year, month0, day){
  return `${year}-${pad2(month0+1)}-${pad2(day)}`;
}
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
// クライアント状態
// ==============================
let reservations = [];
let blockedDates = [];

// ==============================
// API
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
  return Array.from(new Set(out));
}

// APIコール
async function apiUpdateReservation({ id, date, time, category, note }) {
  const res = await fetch('./api.php?action=update&ts=' + Date.now(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    cache: 'no-store',
    body: JSON.stringify({ id, date, time, category, note })
  });
  return res.json();
}

// 例：モーダルや行内フォームから取得して送信
async function onClickSaveEdit(rowId) {
  const date = document.querySelector(`#edit-date-${rowId}`).value;        // "YYYY-MM-DD"
  const time = document.querySelector(`#edit-time-${rowId}`).value;        // "午前" / "午後" など
  const category = document.querySelector(`#edit-category-${rowId}`).value;
  const note = document.querySelector(`#edit-note-${rowId}`).value.trim();

  const r = await apiUpdateReservation({ id: rowId, date, time, category, note });
  if (r && r.ok === false) {
    alert(r.error || '更新に失敗しました');
    return;
  }
  alert('更新しました');
  // 再描画や一覧再取得など
  // await reloadList();
}

// ==============================
// 予約可否判定
// ==============================
function checkReservability({ dateObj, isoDate, hasAnyReservation, todayBase, serverBlockedISO }){
  if (hasAnyReservation) return { blocked: true, reason: "既に予約があります" };
  if (POLICY.useServerBlockedDates && serverBlockedISO.includes(isoDate)) {
    return { blocked: true, reason: "予約不可日（管理者設定）" };
  }
  if (POLICY.blockPastTodayTomorrow) {
    const tomorrow = new Date(todayBase);
    tomorrow.setDate(todayBase.getDate() + 1);
    if (dateObj <= tomorrow) return { blocked: true, reason: "過去・当日・翌日は予約できません" };
  }
  if (POLICY.blockWeekend) {
    const dow = dateObj.getDay();
    if (dow === 0 || dow === 6) return { blocked: true, reason: "土日は予約できません" };
  }
  return { blocked: false, reason: "" };
}

// ==============================
// サーバー→画面メモリ同期
// ==============================
async function syncLocalFromApi(){
  try {
    const list = await apiListReservations();
    reservations = Array.isArray(list) ? list.map(r => ({
      date: isoToJP(r.date),
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
// ==============================
window.addEventListener('DOMContentLoaded', () => {
  const calendar = document.getElementById('calendar');
  const monthLabel = document.getElementById('current-month');
  const prevBtn = document.getElementById('prev-month');
  const nextBtn = document.getElementById('next-month');
  const selectedDateDisplay = document.getElementById('selected-date');
  const reservationForm = document.getElementById('reservation-form');
  const form = document.getElementById('form');

  const today = new Date(); today.setHours(0,0,0,0);
  const todayYear  = today.getFullYear();
  const todayMonth = today.getMonth();
  const todayDate  = today.getDate();     // ← 追加：当日の日付

  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null; // "YYYY年M月D日"

  function renderCalendar(year, month){
    monthLabel.textContent = `${year}年 ${month + 1}月`;
    prevBtn.disabled = (year < todayYear) || (year === todayYear && month <= todayMonth);

    calendar.innerHTML = "";

    const firstDay = new Date(year, month, 1);
    const lastDay  = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    for (let i = 0; i < startDay; i++) {
      const empty = document.createElement('div');
      calendar.appendChild(empty);
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
      const dayEl = document.createElement('div');
      dayEl.className = 'day';

      const dateObj = new Date(year, month, d);
      dateObj.setHours(0,0,0,0);

      const isoDate = ymdISO(year, month, d);
      const jpDate  = `${year}年${month + 1}月${d}日`;

      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      dayEl.appendChild(dateLabel);

      // ★ 当日の赤枠（CSSの .today を利用）
      if (year === todayYear && month === todayMonth && d === todayDate) {
        dayEl.classList.add('today');
      }

      const dayReservations   = reservations.filter(r => r.date === jpDate);
      const hasAnyReservation = dayReservations.length > 0;

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

      const { blocked, reason } = checkReservability({
        dateObj,
        isoDate,
        hasAnyReservation,
        todayBase: today,
        serverBlockedISO: blockedDates
      });

      if (blocked) {
        dayEl.classList.add('reserved');
        dayEl.title = reason;
      } else {
        dayEl.addEventListener('click', () => {
          calendar.querySelectorAll('.day').forEach(el => el.classList.remove('selected'));
          dayEl.classList.add('selected');
          selectedDateDisplay.textContent = `${jpDate} を選択しました`;
          selectedDay = jpDate;
          reservationForm.style.display = 'block';
        });
      }

      calendar.appendChild(dayEl);
    }
  }

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

  syncLocalFromApi().then(() => renderCalendar(currentYear, currentMonth));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const company   = document.getElementById('company').value.trim();
    const time      = document.getElementById('time').value;
    const anonymous = document.getElementById('anonymous').checked;
    const category  = document.getElementById('category').value;
    const note      = document.getElementById('note').value.trim();
    const email     = document.getElementById('email').value.trim();

    if (!company || !time || !category || !selectedDay || !email) {
      alert('企業名・時間・カテゴリ・日付・メールアドレスは必須です');
      return;
    }

    const payload = {
      name: company,
      contact: email,
      date: jpDateToISO(selectedDay),
      time,
      anonymous,
      category,
      note
    };

    try {
      const result = await apiCreateReservation(payload);
      if (!(result && (result.ok === true || typeof result.id !== 'undefined'))) {
        alert('サーバー保存に失敗: ' + (result?.error || '不明なエラー'));
        return;
      }
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
