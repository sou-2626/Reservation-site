// calendar.js

// 一般ユーザーとしてログインしていない場合はログイン画面へ
if (!sessionStorage.getItem('loggedIn')) {
  window.location.href = "login.html";
}

// 管理者ログインへ
function goToAdminLogin() {
  window.location.href = "admin_login.html";
}

// --- localStorage 擬似DB ---
function getDatabase() {
  const data = localStorage.getItem("calendarData");
  return data ? JSON.parse(data) : { blockedDates: [], reservations: [] };
}
function saveDatabase(db) {
  localStorage.setItem("calendarData", JSON.stringify(db));
}

// --- 形式変換（画面⇄API） ---
function jpDateToISO(jp) {
  // 例: "2025年9月19日" -> "2025-09-19"
  const m = jp.match(/^(\d{4})年(\d{1,2})月(\d{1,2})日$/);
  if (!m) return "";
  const [_, y, mo, d] = m;
  return `${y}-${String(+mo).padStart(2, '0')}-${String(+d).padStart(2, '0')}`;
}

function isoToJP(iso) {
  // 例: "2025-09-19" -> "2025年9月19日"
  const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return "";
  const [_, y, mo, d] = m;
  return `${y}年${+mo}月${+d}日`;
}

// 指定した "YYYY年M月D日" に、1件でも予約があるか
function hasReservationOn(dateJP) {
  const db = getDatabase();
  return db.reservations.some(r => r.date === dateJP);
}

// time が "午前" / "午後" のときはそのまま使い、そうでなければ HH:MM から判定
function getAmPmLabel(timeStr) {
  const s = (timeStr || "").trim();
  if (s === "午前" || s === "午後") return s;
  const m = s.match(/^(\d{1,2})/);          // 先頭の“時”だけ拾う
  if (!m) return "";                        // 判定不可なら空
  return parseInt(m[1], 10) < 12 ? "午前" : "午後";
}

// --- API 通信 ---
async function apiCreateReservation(payload) {
  const res = await fetch('./api.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  return res.json();
}

// 起動時に API→localStorage を同期（サーバーの状態に置き換え）
async function syncLocalFromApi() {
  try {
    const list = await fetch('./api.php?action=list').then(r => r.json());
    if (!Array.isArray(list)) return;

    // APIの配列 [{id, name, date(YYYY-MM-DD), time, memo}, ...] を
    // 画面表示用の形に丸ごと変換して置き換える
    const mapped = list.map(r => ({
      date: isoToJP(r.date),   // "YYYY年M月D日"
      time: r.time || "",
      company: r.name || "",
      // APIの memo から最低限の表示だけ保つ（必要ならパース拡張可）
      anonymous: "いいえ",
      category: "",
      note: r.memo || ""
    }));

    const db = getDatabase();
    const keepBlocked = Array.isArray(db.blockedDates) ? db.blockedDates : [];
    const newDb = { blockedDates: keepBlocked, reservations: mapped };

    saveDatabase(newDb);
  } catch (e) {
    console.error('syncLocalFromApi failed', e);
  }
}

window.addEventListener("DOMContentLoaded", () => {
  // 必要要素
  const calendar = document.getElementById('calendar');
  const monthLabel = document.getElementById('current-month');
  const prevBtn = document.getElementById('prev-month');
  const nextBtn = document.getElementById('next-month');
  const selectedDateDisplay = document.getElementById('selected-date');
  const reservationForm = document.getElementById('reservation-form');
  const form = document.getElementById('form');

  // 今日情報
  const todayFull = new Date(); todayFull.setHours(0, 0, 0, 0);
  const todayYear = todayFull.getFullYear();
  const todayMonth = todayFull.getMonth();
  const todayDate = todayFull.getDate();

  // 表示中の年月・選択日
  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null; // "YYYY年M月D日"

  // カレンダー描画
  function renderCalendar(year, month) {
    monthLabel.textContent = `${year}年 ${month + 1}月`;

    // 前月ボタン：当月以下は無効（既存仕様を維持）
    prevBtn.disabled = (year < todayYear) || (year === todayYear && month <= todayMonth);

    const db = getDatabase();
    const blocked = db.blockedDates;       // ["YYYY年M月D日"]
    const reservations = db.reservations;       // 画面表示用

    calendar.innerHTML = "";

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    // 先頭の空白
    for (let i = 0; i < startDay; i++) {
      const empty = document.createElement('div');
      calendar.appendChild(empty);
    }

    // 日付セル
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const dayEl = document.createElement('div');
      dayEl.className = 'day';

      const dateObj = new Date(year, month, d);
      dateObj.setHours(0, 0, 0, 0);
      const jpDate = `${year}年${month + 1}月${d}日`;
      const dow = dateObj.getDay();

      // 日付数字
      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      dayEl.appendChild(dateLabel);

      // その日の予約をまとめて取得（ここが重要）
      const dayReservations = reservations.filter(r => r.date === jpDate);
      const hasAnyReservation = dayReservations.length > 0;

      // 予約バッジ（折り返し表示にする想定、午前/午後 + 名前）
      dayReservations.forEach(r => {
        const fullName = r.anonymous === "はい" ? "匿名" : (r.company || "");
        // r.time が "午前"/"午後" なのでそのまま前置き
        const label = r.time ? `${r.time} ${fullName}` : fullName;

        const badge = document.createElement('div');
        badge.className = 'company-name';
        if (r.category === "CG") badge.style.color = "green";
        else if (r.category === "ゲーム") badge.style.color = "blue";
        else if (r.category === "両方") badge.style.color = "red";

        badge.textContent = label;
        if (r.time) badge.title = `${r.time} に予約`;
        dayEl.appendChild(badge);
      });

      // 今日だけ赤枠
      if (year === todayYear && month === todayMonth && d === todayDate) {
        dayEl.classList.add('today');
      }

      // 予約不可判定
      const tomorrow = new Date(todayFull); tomorrow.setDate(todayFull.getDate() + 1);
      const isPastOrTodayOrTomorrow = (dateObj <= tomorrow);
      const isWeekend = (dow === 0 || dow === 6);
      const isBlocked = blocked.includes(jpDate);

      if (isWeekend || isPastOrTodayOrTomorrow || isBlocked || hasAnyReservation) {
        dayEl.classList.add('reserved');
        dayEl.title = hasAnyReservation
          ? "既に予約があります"
          : (isBlocked
            ? "予約不可日（管理者設定）"
            : (isPastOrTodayOrTomorrow ? "過去・当日・翌日は予約できません" : "土日は予約できません"));
      } else {
        // クリックで選択
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

  // 月移動
  prevBtn.addEventListener('click', () => {
    if (currentMonth > 0) currentMonth--; else { currentMonth = 11; currentYear--; }
    renderCalendar(currentYear, currentMonth);
  });
  nextBtn.addEventListener('click', () => {
    if (currentMonth < 11) currentMonth++; else { currentMonth = 0; currentYear++; }
    renderCalendar(currentYear, currentMonth);
  });

  // 起動時：API→localStorage 同期してから描画（F5で消えないように）
  syncLocalFromApi().then(() => {
    renderCalendar(currentYear, currentMonth);
  });

  // 予約送信（API保存＋ローカルにも反映）
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const company = document.getElementById('company').value.trim();
    const time = document.getElementById('time').value;
    const anonymous = document.getElementById('anonymous').checked;
    const category = document.getElementById('category').value;
    const note = document.getElementById('note').value.trim();

    if (!company || !time || !category || !selectedDay) {
      alert('企業名・時間・カテゴリ・日付は必須です');
      return;
    }

    // 画面側（ローカル表示用）
    const newRes = {
      date: selectedDay,
      time,
      company,
      anonymous: anonymous ? "はい" : "いいえ",
      category,
      note
    };

    try {
      // サーバー保存（PHP + JSON）
      const payload = {
        name: company,
        contact: '',
        date: jpDateToISO(selectedDay), // "YYYY-MM-DD"
        time,
        memo: `匿名:${anonymous ? 'はい' : 'いいえ'} / カテゴリ:${category} / 備考:${note || ''}`
      };
      const result = await apiCreateReservation(payload);
      if (!result.ok) {
        alert('サーバー保存に失敗: ' + (result.error || '不明なエラー'));
        return;
      }

      // ローカルにも追加（短冊表示のため）
      const db = getDatabase();
      db.reservations.push(newRes);
      saveDatabase(db);

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
