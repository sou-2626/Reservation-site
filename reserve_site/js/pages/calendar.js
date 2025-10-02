// js/pages/calendar.js
import { guardUser } from '../core/authGuard.js';
import { listReservations, listBlocked, createReservation } from '../core/api.js';
import { jpDateToISO, isoToJP, ymdISO, normalizeISO } from '../core/date.js';

// 入場ガード（未ログイン→login.htmlへ）
guardUser('login.html');

// 予約ポリシー
const POLICY = {
  blockWeekend: true,
  blockPastTodayTomorrow: true,
  useServerBlockedDates: true
};

// Blocked配列をISO化
function toISOBlockedArray(raw) {
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

function checkReservability({ dateObj, isoDate, hasAnyReservation, todayBase, serverBlockedISO }) {
  if (hasAnyReservation) return { blocked: true, reason: '既に予約があります' };
  if (POLICY.useServerBlockedDates && serverBlockedISO.includes(isoDate)) {
    return { blocked: true, reason: '予約不可日（管理者設定）' };
  }
  if (POLICY.blockPastTodayTomorrow) {
    const tomorrow = new Date(todayBase);
    tomorrow.setDate(todayBase.getDate() + 1);
    if (dateObj <= tomorrow) return { blocked: true, reason: '過去・当日・翌日は予約できません' };
  }
  if (POLICY.blockWeekend) {
    const dow = dateObj.getDay();
    if (dow === 0 || dow === 6) return { blocked: true, reason: '土日は予約できません' };
  }
  return { blocked: false, reason: '' };
}

async function init() {
  // DOM取得
  const calendar = document.getElementById('calendar');
  const monthLabel = document.getElementById('current-month');
  const prevBtn = document.getElementById('prev-month');
  const nextBtn = document.getElementById('next-month');
  const selectedDateDisplay = document.getElementById('selected-date');
  const reservationForm = document.getElementById('reservation-form');
  const form = document.getElementById('form');

  // 今日
  const today = new Date(); today.setHours(0,0,0,0);
  const todayYear = today.getFullYear();
  const todayMonth = today.getMonth();
  const todayDate = today.getDate();

  // 状態
  let reservations = [];
  let blockedDates = [];
  let currentYear = todayYear;
  let currentMonth = todayMonth;
  let selectedDay = null; // "YYYY年M月D日"

  // 描画
  function renderCalendar(year, month) {
    monthLabel.textContent = `${year}年 ${month + 1}月`;
    prevBtn.disabled = (year < todayYear) || (year === todayYear && month <= todayMonth);
    calendar.innerHTML = '';

    const firstDay = new Date(year, month, 1);
    const lastDay  = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();

    for (let i = 0; i < startDay; i++) calendar.appendChild(document.createElement('div'));

    for (let d = 1; d <= lastDay.getDate(); d++) {
      const dayEl = document.createElement('div');
      dayEl.className = 'day';

      const dateObj = new Date(year, month, d); dateObj.setHours(0,0,0,0);
      const isoDate = ymdISO(year, month, d);
      const jpDate  = `${year}年${month + 1}月${d}日`;

      const dateLabel = document.createElement('div');
      dateLabel.className = 'date';
      dateLabel.textContent = d;
      dayEl.appendChild(dateLabel);

      // 今日に赤枠
      if (year === todayYear && month === todayMonth && d === todayDate) {
        dayEl.classList.add('today');
      }

      // その日の予約
      const dayReservations = reservations.filter(r => r.date === jpDate);
      const hasAnyReservation = dayReservations.length > 0;

      dayReservations.forEach(r => {
        const fullName = r.anonymous === 'はい' ? '匿名' : (r.company || '');
        const label = r.time ? `${r.time} ${fullName}` : fullName;
        const badge = document.createElement('div');
        badge.className = 'company-name';
        if (r.category === 'CG') badge.style.color = 'green';
        else if (r.category === 'ゲーム') badge.style.color = 'blue';
        else if (r.category === '両方') badge.style.color = 'red';
        badge.textContent = label;
        if (r.time) badge.title = `${r.time} に予約`;
        dayEl.appendChild(badge);
      });

      // 予約可否
      const { blocked, reason } = checkReservability({
        dateObj, isoDate, hasAnyReservation, todayBase: today, serverBlockedISO: blockedDates
      });

      if (blocked) {
        dayEl.classList.add('reserved');
        dayEl.title = reason;
      } else {
        dayEl.addEventListener('click', () => {
          calendar.querySelectorAll('.day').forEach(el => el.classList.remove('selected'));
          dayEl.classList.add('selected');
          selectedDay = jpDate;
          selectedDateDisplay.textContent = `${jpDate} を選択しました`;
          reservationForm.style.display = 'block';
        });
      }

      calendar.appendChild(dayEl);
    }
  }

  // 月移動
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

  // 初回データ同期 → 描画
  async function load() {
    const list = await listReservations();
    reservations = Array.isArray(list)
      ? list.map(r => ({
          date: isoToJP(r.date),
          time: r.time || '',
          company: r.name || '',
          anonymous: (r.anonymous === true || r.anonymous === 'はい') ? 'はい'
                    : (r.anonymous === 'いいえ' ? 'いいえ' : (r.anonymous || 'いいえ')),
          category: r.category || '',
          note: r.note || ''
        }))
      : [];
    blockedDates = toISOBlockedArray(await listBlocked());
    renderCalendar(currentYear, currentMonth);
  }
  await load();

  // 予約送信
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
      const res = await createReservation(payload);
      if (!(res && (res.ok === true || typeof res.id !== 'undefined'))) {
        alert('サーバー保存に失敗: ' + (res?.error || '不明なエラー'));
        return;
      }
      alert(`予約を受け付けました！\n${selectedDay} ${time}`);
      e.target.reset();
      reservationForm.style.display = 'none';
      await load();
    } catch (err) {
      console.error(err);
      alert('通信エラーで保存できませんでした');
    }
  });
}

// 自動初期化（index.html から <script type="module" src="..."> でOK）
document.addEventListener('DOMContentLoaded', init);
