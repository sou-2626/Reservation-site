// calendar.js

// 一般ユーザーとしてログインしていない場合はログイン画面へリダイレクト
if (!sessionStorage.getItem('loggedIn')) {
  window.location.href = "login.html";
}

// 管理者ログイン画面へ遷移する関数（管理者ログインボタンなどで使用）
function goToAdminLogin() {
  window.location.href = "admin_login.html";
}

// 擬似データベースを取得（localStorageから）
function getDatabase() {
  const data = localStorage.getItem("calendarData");
  return data ? JSON.parse(data) : { blockedDates: [], reservations: [] };
}

// 擬似データベースを保存
function saveDatabase(db) {
  localStorage.setItem("calendarData", JSON.stringify(db));
}

window.addEventListener("DOMContentLoaded", () => {
  const calendar = document.getElementById('calendar');
  const monthLabel = document.getElementById('current-month');
  const prevBtn = document.getElementById('prev-month');
  const nextBtn = document.getElementById('next-month');
  const selectedDateDisplay = document.getElementById('selected-date');
  const reservationForm = document.getElementById('reservation-form');
  const form = document.getElementById('form');

  const todayFull = new Date();
  todayFull.setHours(0, 0, 0, 0);
  const todayYear = todayFull.getFullYear();
  const todayMonth = todayFull.getMonth();
  const todayDate = todayFull.getDate();

  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null;

  // カレンダーを描画する関数
  function renderCalendar(year, month) {
    // 表示している月のラベル更新
    monthLabel.textContent = `${year}年 ${month + 1}月`;

    // 現在の月以前には戻れないように制御
    if (year < todayYear || (year === todayYear && month <= todayMonth)) {
      prevBtn.disabled = true;
    } else {
      prevBtn.disabled = false;
    }

    // カレンダー内をクリア
    calendar.innerHTML = "";

    // 予約不可日データを取得
    const db = getDatabase();
    const blocked = db.blockedDates;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    // カレンダーの最初の空白を作成
    for (let i = 0; i < startDay; i++) {
      const empty = document.createElement('div');
      calendar.appendChild(empty);
    }

    // 日付を1日ずつ生成して配置
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const day = document.createElement('div');
      day.textContent = d;
      day.className = 'day';

      const dateObj = new Date(year, month, d);
      dateObj.setHours(0, 0, 0, 0);
      const isoDate = dateObj.toISOString().split('T')[0];
      const dayOfWeek = dateObj.getDay();

      const tomorrow = new Date(todayFull);
      tomorrow.setDate(todayFull.getDate() + 1);

      const isPastOrTodayOrTomorrow = (dateObj <= tomorrow);
      const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
      const isBlockedDate = blocked.includes(isoDate);

      if (year === todayYear && month === todayMonth && d === todayDate) {
        day.classList.add('today');
      }

      // 予約できない日（過去・土日・翌日・管理者設定の不可日）
      if (isWeekend || isPastOrTodayOrTomorrow || isBlockedDate) {
        day.classList.add('reserved');
        if (isBlockedDate) {
          day.title = "予約不可日（管理者設定）";
        } else if (isPastOrTodayOrTomorrow) {
          day.title = "過去・当日・翌日は予約できません";
        } else if (isWeekend) {
          day.title = "土日は予約できません";
        }
      } else {
        // 予約可能な日：クリックで選択・フォーム表示
        day.addEventListener('click', () => {
          document.querySelectorAll('.day').forEach(el => el.classList.remove('selected'));
          day.classList.add('selected');
          selectedDateDisplay.textContent = `${year}年${month + 1}月${d}日 を選択しました`;
          selectedDay = isoDate;
          reservationForm.style.display = 'block';
        });
      }

      calendar.appendChild(day);
    }
  }

  // 前月ボタン
  prevBtn.addEventListener('click', () => {
    if (currentMonth > 0) {
      currentMonth--;
    } else {
      currentMonth = 11;
      currentYear--;
    }
    renderCalendar(currentYear, currentMonth);
  });

  // 次月ボタン
  nextBtn.addEventListener('click', () => {
    if (currentMonth < 11) {
      currentMonth++;
    } else {
      currentMonth = 0;
      currentYear++;
    }
    renderCalendar(currentYear, currentMonth);
  });

  // 最初のカレンダー表示
  renderCalendar(currentYear, currentMonth);

  // フォーム送信処理（予約データを保存）
  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const company = document.getElementById('company').value;
    const time = document.getElementById('time').value;
    const anonymous = document.getElementById('anonymous').checked;
    const category = document.getElementById('category').value;
    const note = document.getElementById('note').value;

    if (!company || !time || !category || !selectedDay) {
      alert('企業名・時間・カテゴリ・日付は必須です');
      return;
    }

    const newRes = {
      date: selectedDay,
      time: time,
      company: company,
      anonymous: anonymous ? "はい" : "いいえ",
      category: category,
      note: note
    };

    const db = getDatabase();
    db.reservations.push(newRes);
    saveDatabase(db);

    alert(`予約を受け付けました！\n${selectedDay} ${time}`);
    form.reset();
    reservationForm.style.display = 'none';
    renderCalendar(currentYear, currentMonth);
  });
});
