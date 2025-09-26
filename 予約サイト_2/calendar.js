// calendar.js v3 (no-localStorage)

// 一般ユーザーとしてログインしていない場合はログイン画面へ
if (!sessionStorage.getItem('loggedIn')) {
  window.location.href = "login.html";
}

// 管理者ログインへ
function goToAdminLogin() {
  window.location.href = "admin_login.html";
}

// --- 形式変換（画面⇄API） ---
function jpDateToISO(jp) {
  const m = jp.match(/^(\d{4})年(\d{1,2})月(\d{1,2})日$/);
  if (!m) return "";
  const [_, y, mo, d] = m;
  return `${y}-${String(+mo).padStart(2, '0')}-${String(+d).padStart(2, '0')}`;
}
function isoToJP(iso) {
  const m = iso?.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return iso ?? "";
  const [_, y, mo, d] = m;
  return `${y}年${+mo}月${+d}日`;
}

// --- ここから：画面内メモリだけで持つ ---
let reservations = [];   // API /list の内容（表示用にマッピングした配列）
let blockedDates = [];   // 予約不可日（今は使っていなければ空のまま）

// --- API 通信 ---
async function apiCreateReservation(payload) {
  const res = await fetch('./api.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  return res.json();
}
async function apiListReservations() {
  const res = await fetch('./api.php?action=list');
  return res.json();
}

// 起動時に API→メモリ を同期（サーバーの状態に置き換え）
async function syncLocalFromApi() {
  try {
    const list = await apiListReservations();
    if (!Array.isArray(list)) return;

    reservations = list.map(r => ({
      date: isoToJP(r.date),   // "YYYY年M月D日"
      time: r.time || "",
      company: r.name || "",
      anonymous: (r.anonymous === true || r.anonymous === "はい") ? "はい"
               : (r.anonymous === "いいえ" ? "いいえ" : (r.anonymous || "いいえ")),
      category: r.category || "",
      note: r.note || ""
    }));
    // blockedDates は今は外部管理が無ければ何もしない
  } catch (e) {
    console.error('syncLocalFromApi failed', e);
  }
}

window.addEventListener("DOMContentLoaded", () => {
  const calendar = document.getElementById('calendar');
  const monthLabel = document.getElementById('current-month');
  const prevBtn = document.getElementById('prev-month');
  const nextBtn = document.getElementById('next-month');
  const selectedDateDisplay = document.getElementById('selected-date');
  const reservationForm = document.getElementById('reservation-form');
  const form = document.getElementById('form');

  const todayFull = new Date(); todayFull.setHours(0,0,0,0);
  const todayYear = todayFull.getFullYear();
  const todayMonth = todayFull.getMonth();
  const todayDate = todayFull.getDate();

  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null; // "YYYY年M月D日"

  function renderCalendar(year, month) {
    monthLabel.textContent = `${year}年 ${month + 1}月`;
    // 前月ボタン：当月以下は無効
    prevBtn.disabled = (year < todayYear) || (year === todayYear && month <= todayMonth);

    const blocked = blockedDates;   // 予約不可日（必要なら別途実装）
    const dayList = reservations;   // 予約一覧（メモリ）

    calendar.innerHTML = "";

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
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
      const jpDate = `${year}年${month + 1}月${d}日`;
      const dow = dateObj.getDay();

      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      dayEl.appendChild(dateLabel);

      const dayReservations = dayList.filter(r => r.date === jpDate);
      const hasAnyReservation = dayReservations.length > 0;

      dayReservations.forEach(r => {
        const fullName = r.anonymous === "はい" ? "匿名" : (r.company || "");
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

      if (year === todayYear && month === todayMonth && d === todayDate) {
        dayEl.classList.add('today');
      }

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
    if (currentMonth > 0) currentMonth--; else { currentMonth = 11; currentYear--; }
    renderCalendar(currentYear, currentMonth);
  });
  nextBtn.addEventListener('click', () => {
    if (currentMonth < 11) currentMonth++; else { currentMonth = 0; currentYear++; }
    renderCalendar(currentYear, currentMonth);
  });

  // 起動時：API→メモリ同期→描画
  syncLocalFromApi().then(() => {
    renderCalendar(currentYear, currentMonth);
  });

  // 予約送信
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

    try {
      const payload = {
        name: company,
        contact: '',
        date: jpDateToISO(selectedDay),
        time,
        anonymous,       // true/false
        category,
        note
      };

      const result = await apiCreateReservation(payload);
      if (!(result && (result.ok === true || typeof result.id !== 'undefined'))) {
        alert('サーバー保存に失敗: ' + (result?.error || '不明なエラー'));
        return;
      }

      // 保存成功 → サーバーから最新を再取得して描画
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
