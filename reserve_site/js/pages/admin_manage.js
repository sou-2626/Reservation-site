// js/pages/admin_manage.js

// --- Admin guard ---
if (sessionStorage.getItem('role') !== 'admin' || sessionStorage.getItem('loggedIn') !== 'true') {
  location.replace('admin_login.html');
}

// --- API base resolver (./ → ../ の順で探す) ---
let API_BASE = '';
async function resolveApiBase() {
  try {
    const r = await fetch(`./api.php?action=blocked_list&ts=${Date.now()}`, { cache: 'no-store' });
    if (r.ok) { API_BASE = '.'; return; }
  } catch {}
  try {
    const r = await fetch(`../api.php?action=blocked_list&ts=${Date.now()}`, { cache: 'no-store' });
    if (r.ok) { API_BASE = '..'; return; }
  } catch {}
  throw new Error('api.php が見つかりません（./ または ../）');
}

// --- State ---
const selectedBlockedDates = new Set();
let currentBlockedDates = [];
let currentYear, currentMonth;

// --- API helpers ---
async function fetchBlockedDates() {
  const res = await fetch(`${API_BASE}/api.php?action=blocked_list&ts=${Date.now()}`, { cache: 'no-store' });
  const raw = await res.json();
  currentBlockedDates = Array.isArray(raw)
    ? raw.map(d => typeof d === 'string' ? d : d.date || d['日付']).filter(Boolean)
    : [];
}
async function addBlocked(ymd) {
  await fetch(`${API_BASE}/api.php?action=blocked_add&ts=${Date.now()}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    body: JSON.stringify({ date: ymd })
  });
}
async function delBlocked(ymd) {
  await fetch(`${API_BASE}/api.php?action=blocked_delete&ts=${Date.now()}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    body: JSON.stringify({ date: ymd })
  });
}

// --- UI ---
function renderSelectableCalendar(year, month) {
  document.getElementById('current-month').textContent = `${year}年 ${month + 1}月`;

  const calendarEl = document.getElementById('multi-calendar'); // ← HTMLに合わせたID
  calendarEl.innerHTML = '';

  const today = new Date(); today.setHours(0,0,0,0);
  const firstDay = new Date(year, month, 1);
  const lastDay  = new Date(year, month + 1, 0);
  const startDay = firstDay.getDay();

  for (let i = 0; i < startDay; i++) calendarEl.appendChild(document.createElement('div'));

  for (let d = 1; d <= lastDay.getDate(); d++) {
    const dateObj = new Date(year, month, d);
    const isoDate = `${year}-${String(month + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const dow = dateObj.getDay();

    const dayEl = document.createElement('div');
    dayEl.className = 'day';

    if (year === today.getFullYear() && month === today.getMonth() && d === today.getDate()) {
      dayEl.classList.add('today');
    }
    if (currentBlockedDates.includes(isoDate)) {
      dayEl.classList.add('blocked');
    }
    if (dow === 0 || dow === 6) {
      dayEl.classList.add('disabled'); // 週末は選択不可（元コード踏襲）
    }

    const dateLabel = document.createElement('div');
    dateLabel.className = 'date';
    dateLabel.textContent = d;
    dayEl.appendChild(dateLabel);

    if (!dayEl.classList.contains('disabled')) {
      dayEl.addEventListener('click', () => {
        if (selectedBlockedDates.has(isoDate)) {
          selectedBlockedDates.delete(isoDate);
          dayEl.classList.remove('multi-selected');
        } else {
          selectedBlockedDates.add(isoDate);
          dayEl.classList.add('multi-selected');
        }
      });
    }

    calendarEl.appendChild(dayEl);
  }
}

// --- Actions (HTMLからonclickで呼ぶ) ---
async function submitSelectedBlockedDates() {
  if (selectedBlockedDates.size === 0) return alert('日付が選択されていません');
  for (const ymd of selectedBlockedDates) await addBlocked(ymd);
  alert('追加しました');
  selectedBlockedDates.clear();
  await fetchBlockedDates();
  renderSelectableCalendar(currentYear, currentMonth);
}
async function submitUnblockedDates() {
  if (selectedBlockedDates.size === 0) return alert('日付が選択されていません');
  for (const ymd of selectedBlockedDates) await delBlocked(ymd);
  alert('解除しました');
  selectedBlockedDates.clear();
  await fetchBlockedDates();
  renderSelectableCalendar(currentYear, currentMonth);
}
function showBlockedList() {
  const listEl = document.getElementById('blocked-list');
  listEl.innerHTML = '';

  currentBlockedDates.sort().forEach(date => {
    const li = document.createElement('li');

    const label = document.createElement('span');
    const d = new Date(date);
    label.textContent = `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日`;

    const delBtn = document.createElement('button');
    delBtn.textContent = '削除';
    delBtn.onclick = async () => {
      await delBlocked(date);
      await fetchBlockedDates();
      renderSelectableCalendar(currentYear, currentMonth);
      showBlockedList();
    };

    li.appendChild(label);
    li.appendChild(delBtn);
    listEl.appendChild(li);
  });

  document.getElementById('blocked-modal').classList.remove('hidden');
}
function hideBlockedList() {
  document.getElementById('blocked-modal').classList.add('hidden');
}

// --- Init ---
document.addEventListener('DOMContentLoaded', async () => {
  await resolveApiBase();

  const today = new Date();
  currentYear = today.getFullYear();
  currentMonth = today.getMonth();

  await fetchBlockedDates();
  renderSelectableCalendar(currentYear, currentMonth);

  // month nav
  document.getElementById('prev-month').onclick = () => {
    const now = new Date();
    if (currentYear > now.getFullYear() || (currentYear === now.getFullYear() && currentMonth > now.getMonth())) {
      if (currentMonth === 0) { currentMonth = 11; currentYear--; }
      else { currentMonth--; }
      renderSelectableCalendar(currentYear, currentMonth);
    }
  };
  document.getElementById('next-month').onclick = () => {
    if (currentMonth === 11) { currentMonth = 0; currentYear++; }
    else { currentMonth++; }
    renderSelectableCalendar(currentYear, currentMonth);
  };

  // expose globals for HTML onclick
  window.submitSelectedBlockedDates = submitSelectedBlockedDates;
  window.submitUnblockedDates = submitUnblockedDates;
  window.showBlockedList = showBlockedList;
  window.hideBlockedList = hideBlockedList;
});
