// js/pages/admin_manage.js

// --- 管理者ガード ---
if (sessionStorage.getItem('role') !== 'admin' || sessionStorage.getItem('loggedIn') !== 'true') {
  location.replace('admin_login.html');
}

// --- APIベースURL解決（./ → ../ の順で確認） ---
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

// --- 状態管理 ---
const selectedBlockedDates = new Set();
let currentBlockedDates = [];
let currentYear, currentMonth;

// --- API呼び出し ---
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

// --- カレンダー描画 ---
function renderSelectableCalendar(year, month) {
  document.getElementById('current-month').textContent = `${year}年 ${month + 1}月`;
  const calendarEl = document.getElementById('multi-calendar');
  calendarEl.innerHTML = '';

  const today = new Date(); today.setHours(0, 0, 0, 0);
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const startDay = firstDay.getDay();

  // 空白セル（1日が始まる曜日まで）
  for (let i = 0; i < startDay; i++) {
    calendarEl.appendChild(document.createElement('div'));
  }

  // 日付セル生成
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const isoDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const dateObj = new Date(year, month, d);
    const dow = dateObj.getDay();

    const dayEl = document.createElement('div');
    dayEl.className = 'day';

    // 今日
    if (year === today.getFullYear() && month === today.getMonth() && d === today.getDate()) {
      dayEl.classList.add('today');
    }

    // 予約不可日
    if (currentBlockedDates.includes(isoDate)) {
      dayEl.classList.add('blocked');
    }

    // 週末を無効化（クリック禁止）
    if (dow === 0 || dow === 6) {
      dayEl.classList.add('disabled');
    }

    // 日付ラベル
    const label = document.createElement('div');
    label.className = 'date';
    label.textContent = d;
    dayEl.appendChild(label);

    // クリック可能セル
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

// --- 操作関数 ---
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

  if (currentBlockedDates.length === 0) {
    listEl.innerHTML = '<li>予約不可日は登録されていません</li>';
  } else {
    currentBlockedDates.sort().forEach(date => {
      const li = document.createElement('li');

      const label = document.createElement('span');
      const d = new Date(date);
      label.textContent = `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;

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
  }

  const modal = document.getElementById('blocked-modal');
  modal.classList.add('active');
}

function hideBlockedList() {
  document.getElementById('blocked-modal').classList.remove('active');
}

// --- 初期化 ---
document.addEventListener('DOMContentLoaded', async () => {
  await resolveApiBase();

  const today = new Date();
  currentYear = today.getFullYear();
  currentMonth = today.getMonth();

  await fetchBlockedDates();
  renderSelectableCalendar(currentYear, currentMonth);

  // 月切り替え
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

  // グローバル公開
  window.submitSelectedBlockedDates = submitSelectedBlockedDates;
  window.submitUnblockedDates = submitUnblockedDates;
  window.showBlockedList = showBlockedList;
  window.hideBlockedList = hideBlockedList;
});
