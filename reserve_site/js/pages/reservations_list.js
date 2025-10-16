// js/pages/reservations_list.js
import { listReservations, deleteReservation, updateReservation } from '../core/api.js';

// --- Guard: admin only ---
if (sessionStorage.getItem('role') !== 'admin' || sessionStorage.getItem('loggedIn') !== 'true') {
  location.replace('admin_login.html');
}

// --- Utils ---
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
const esc = (s) => String(s ?? '');
const isoToSlash = (iso) => {
  const m = iso?.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  return m ? `${m[1]}/${m[2]}/${m[3]}` : (iso ?? '');
};
const hasNote = (v) => (v ?? '').toString().trim().length > 0;

// --- State (edit modal) ---
let currentEditingId = null;

// --- DOM refs (modal) ---
const $backdrop   = $('#modal-backdrop');
const $mName      = $('#m-name');
const $mAnon      = $('#m-anon');
const $mEmail     = $('#m-email');
const $mDate      = $('#m-date');
const $mCat       = $('#m-cat');
const $mNote      = $('#m-note');
const $timeCustom = $('#m-time-custom');
const $btnEdit    = $('#m-edit');
const $btnSave    = $('#m-save');
const $btnCancel  = $('#m-cancel');

// --- Init ---
document.addEventListener('DOMContentLoaded', init);

async function init() {
  await loadList();

  // table actions (delegate)
  document.addEventListener('click', async (ev) => {
    const btnDel = ev.target.closest('button.delete');
    if (btnDel) {
      const tr = btnDel.closest('tr');
      if (!tr) return;
      onDelete(tr);
      return;
    }
    const btnView = ev.target.closest('button.view');
    if (btnView) {
      const tr = btnView.closest('tr');
      if (!tr) return;
      openModal(tr);
      return;
    }
  });

  // modal events
  $btnEdit?.addEventListener('click', () => setReadonlyMode(false));
  $btnSave?.addEventListener('click', onSave);
  $btnCancel?.addEventListener('click', closeModal);
  $backdrop?.addEventListener('click', (e) => { if (e.target === $backdrop) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && $backdrop?.style.display === 'flex') closeModal(); });

  // time radio => toggle custom
  $$('input[name="m-time"]').forEach(r => {
    r.addEventListener('change', () => {
      if (r.value === '_custom') { $timeCustom.classList.remove('hidden'); $timeCustom.removeAttribute('readonly'); $timeCustom.focus(); }
      else { $timeCustom.classList.add('hidden'); $timeCustom.value = ''; $timeCustom.setAttribute('readonly', 'readonly'); }
    });
  });
}

// --- Load & render ---
async function loadList() {
  const tbody = $('#reservation-table tbody');
  const msg = $('#msg');
  tbody.innerHTML = '';
  msg.textContent = '読み込み中…';

  try {
    const list = await listReservations(); // [{id,date,time,name,contact,anonymous,category,note,...}]
    const rows = Array.isArray(list) ? list.slice() : [];
    rows.sort((a, b) => `${a.date || ''}-${a.time || ''}`.localeCompare(`${b.date || ''}-${b.time || ''}`));

    if (!rows.length) { msg.textContent = '予約はありません'; return; }
    msg.textContent = '';

    for (const r of rows) appendRow({
      id: r.id ?? '',
      date: r.date ?? '',
      time: r.time ?? '',
      name: r.name ?? '',
      contact: r.contact ?? '',
      anonymous: r.anonymous === true || r.anonymous === 'はい' ? 'はい'
               : (r.anonymous === 'いいえ' ? 'いいえ' : (r.anonymous ?? 'いいえ')),
      category: r.category ?? '',
      note: r.note ?? ''
    });
  } catch (e) {
    console.error(e);
    msg.textContent = '読み取りが失敗しました（通信エラー）';
  }
}

function appendRow(row) {
  const tbody = $('#reservation-table tbody');
  const tr = document.createElement('tr');
  tr.dataset.id    = row.id;
  tr.dataset.note  = row.note;
  tr.dataset.cat   = row.category;
  tr.dataset.time  = row.time;
  tr.dataset.email = row.contact;

  const noteExists = hasNote(row.note);
  const notePreview = (row.note || '').toString().trim().slice(0, 40);

  tr.innerHTML = `
    <td class="c-date" data-iso="${esc(row.date)}" title="${esc(row.date)}">${esc(isoToSlash(row.date))}</td>
    <td class="c-time" title="${esc(row.time)}">${esc(row.time)}</td>
    <td class="c-name" title="${esc(row.name)}">${esc(row.name)}</td>
    <td class="c-anon">${esc(row.anonymous)}</td>
    <td class="c-cat">${esc(row.category)}</td>
    <td class="c-note-flag">
      <span class="note-indicator" title="${noteExists ? esc(notePreview || '備考あり') : '備考なし'}">
        <span class="note-dot ${noteExists ? 'on' : 'off'}" aria-hidden="true"></span>
        <span>${noteExists ? '備考あり' : '備考なし'}</span>
      </span>
    </td>
    <td class="cell-actions">
      <button class="btn btn-secondary view">確認</button>
      <button class="btn btn-danger delete">削除</button>
    </td>
  `;
  tbody.appendChild(tr);
}

// --- Delete ---
async function onDelete(tr) {
  const id = Number(tr.dataset.id);
  if (!id) return;
  if (!confirm('削除します。よろしいですか？')) return;

  try {
    const r = await deleteReservation(id);
    if (r && r.ok === true) {
      tr.remove();
      if (!$('#reservation-table tbody tr')) $('#msg').textContent = '予約はありません';
    } else {
      alert('削除失敗: ' + (r?.error || '不明なエラー'));
    }
  } catch (e) {
    console.error(e);
    alert('通信エラーで削除できませんでした');
  }
}

// --- Modal open/close ---
function setReadonlyMode(readonly) {
  if (!$mEmail) return;
  $mEmail.readOnly = readonly; $mEmail.classList.toggle('readonly', readonly);
  $mDate.readOnly  = readonly; $mDate.disabled = readonly; $mDate.classList.toggle('readonly', readonly);
  $mCat.disabled   = readonly; $mCat.classList.toggle('readonly', readonly);
  $mNote.readOnly  = readonly; $mNote.classList.toggle('readonly', readonly);
  $$('input[name="m-time"]').forEach(r => r.disabled = readonly);
  if (readonly) $timeCustom.setAttribute('readonly', 'readonly'); else $timeCustom.removeAttribute('readonly');

  $btnEdit.classList.toggle('hidden', !readonly);
  $btnSave.classList.toggle('hidden', readonly);
}

function openModal(tr) {
  currentEditingId = Number(tr.dataset.id);

  const iso  = tr.querySelector('.c-date').getAttribute('data-iso') || '';
  const time = (tr.dataset.time || tr.querySelector('.c-time').textContent || '').trim();
  const name = tr.querySelector('.c-name').textContent.trim();
  const anon = tr.querySelector('.c-anon').textContent.trim();
  const cat  = (tr.dataset.cat || tr.querySelector('.c-cat').textContent || '').trim();
  const note = (tr.dataset.note || '').toString();
  const email= (tr.dataset.email || '').toString();

  $mName.textContent = name || '(未入力)';
  $mAnon.textContent = anon || 'いいえ';
  $mEmail.value = email;
  $mDate.value = iso;
  $mCat.value = cat || '';
  $mNote.value = note || '';

  // time radio
  $$('input[name="m-time"]').forEach(r => r.checked = false);
  $timeCustom.classList.add('hidden');
  $timeCustom.value = '';
  if (time === '午前' || time === '午後') {
    const r = $(`input[name="m-time"][value="${time}"]`);
    if (r) r.checked = true;
  } else if (time) {
    const r = $(`input[name="m-time"][value="_custom"]`);
    if (r) r.checked = true;
    $timeCustom.classList.remove('hidden');
    $timeCustom.value = time;
  } else {
    const r = $(`input[name="m-time"][value="午前"]`);
    if (r) r.checked = true;
  }

  setReadonlyMode(true);
  $backdrop.style.display = 'flex';
  $backdrop.setAttribute('aria-hidden', 'false');
}

function closeModal() {
  currentEditingId = null;
  $backdrop.style.display = 'none';
  $backdrop.setAttribute('aria-hidden', 'true');
}

// --- Save (update) ---
async function onSave() {
  const id = currentEditingId;
  if (!id) { alert('内部IDが取得できませんでした'); return; }

  const email = $mEmail.value.trim();
  const date  = $mDate.value;
  const cat   = $mCat.value;
  const note  = $mNote.value.trim();

  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { alert('日付はYYYY-MM-DDで指定してください'); return; }

  const checked = $('input[name="m-time"]:checked');
  let time = '';
  if (checked) {
    if (checked.value === '_custom') {
      time = $timeCustom.value.trim();
      if (!time) { alert('その他の時間を入力してください'); return; }
    } else {
      time = checked.value;
    }
  }

  try {
    const res = await updateReservation({ id, date, time, category: cat, note, contact: email });
    if (res && res.ok === false) {
      alert(res.error || '更新に失敗しました');
      return;
    }

    // Reflect to table
    const tr = $(`#reservation-table tbody tr[data-id="${id}"]`);
    if (tr) {
      tr.querySelector('.c-date').setAttribute('data-iso', date);
      tr.querySelector('.c-date').textContent = isoToSlash(date);
      tr.querySelector('.c-time').textContent = time;
      tr.querySelector('.c-cat').textContent  = cat;
      tr.dataset.note  = note;
      tr.dataset.time  = time;
      tr.dataset.cat   = cat;
      tr.dataset.email = email;

      const noteExists = hasNote(note);
      const preview = (note || '').toString().trim().slice(0, 40);
      const cell = tr.querySelector('.c-note-flag');
      cell.innerHTML = `
        <span class="note-indicator" title="${noteExists ? esc(preview || '備考あり') : '備考なし'}">
          <span class="note-dot ${noteExists ? 'on' : 'off'}" aria-hidden="true"></span>
          <span>${noteExists ? '備考あり' : '備考なし'}</span>
        </span>`;
    }

    setReadonlyMode(true);
    closeModal();
  } catch (e) {
    console.error(e);
    alert('通信エラーで更新できませんでした');
  }
}
