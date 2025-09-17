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
    monthLabel.textContent = `${year}年 ${month + 1}月`;

    if (year < todayYear || (year === todayYear && month <= todayMonth)) {
      prevBtn.disabled = true;
    } else {
      prevBtn.disabled = false;
    }

    calendar.innerHTML = "";

    const db = getDatabase();
    const blocked = db.blockedDates;
    const reservations = db.reservations;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    for (let i = 0; i < startDay; i++) {
      const empty = document.createElement('div');
      calendar.appendChild(empty);
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
      const day = document.createElement('div');
      day.className = 'day';

      const dateObj = new Date(year, month, d);
      dateObj.setHours(0, 0, 0, 0);
      const correctedDateObj = new Date(dateObj);
      correctedDateObj.setMinutes(correctedDateObj.getMinutes() + correctedDateObj.getTimezoneOffset());
      const isoDate = `${year}年${month + 1}月${d}日`;

      const dayOfWeek = dateObj.getDay();

      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      day.appendChild(dateLabel);

      const companyList = document.createElement('div');
      companyList.className = 'company';
      const dayReservations = db.reservations.filter(r => r.date === isoDate);
      dayReservations.forEach(r => {
        const name = r.anonymous === "はい" ? "匿名" : r.company;
        const shortName = name.length > 5 ? name.slice(0, 5) + "…" : name;
        const bar = document.createElement("div");
        bar.className = "company-name";
        if (r.category === "CG") bar.style.color = "green";
        else if (r.category === "ゲーム") bar.style.color = "blue";
        else if (r.category === "両方") bar.style.color = "red";
        bar.textContent = shortName;
        day.appendChild(bar);
      });

      calendar.appendChild(day);

      const tomorrow = new Date(todayFull);
      tomorrow.setDate(todayFull.getDate() + 1);

      const isPastOrTodayOrTomorrow = (dateObj <= tomorrow);
      const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
      const isBlockedDate = blocked.includes(isoDate);

      if (year === todayYear && month === todayMonth && d === todayDate) {
        day.classList.add('today');
      }

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

  prevBtn.addEventListener('click', () => {
    if (currentMonth > 0) {
      currentMonth--;
    } else {
      currentMonth = 11;
      currentYear--;
    }
    renderCalendar(currentYear, currentMonth);
  });

  nextBtn.addEventListener('click', () => {
    if (currentMonth < 11) {
      currentMonth++;
    } else {
      currentMonth = 0;
      currentYear++;
    }
    renderCalendar(currentYear, currentMonth);
  });

  renderCalendar(currentYear, currentMonth);

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
      date: selectedDay,  // クリック時に確定した日付をそのまま使う
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