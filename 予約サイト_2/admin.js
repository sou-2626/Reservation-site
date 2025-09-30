function adminLogin() {
  const username = document.getElementById("username").value;
  const password = document.getElementById("password").value;

  const db = JSON.parse(localStorage.getItem("calendarData"));
  const admin = db?.accounts?.admin;

  if (admin && username === admin.id && password === admin.pass) {
    sessionStorage.setItem("isAdminLoggedIn", true);
    window.location.href = "dashboard.html";
  } else {
    document.getElementById("error-message").textContent = "ログイン失敗しました";
  }
}

(function () {
  function $(id){ return document.getElementById(id); }
  function pad2(n){ return String(n).padStart(2,'0'); }

  // input[type="date"] の value ("YYYY-MM-DD") -> ローカルDate(0:00)
  function parseYmdToLocalDate(ymd){
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd || "");
    if(!m) return null;
    const y = +m[1], mo = +m[2], d = +m[3];
    return new Date(y, mo-1, d);
  }
  // ローカルDate -> "YYYY-MM-DD"
  function formatLocalDateToYmd(date){
    return `${date.getFullYear()}-${pad2(date.getMonth()+1)}-${pad2(date.getDate())}`;
  }
  function addDaysLocal(date, days){
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
  }

  async function apiBlockedAdd(ymd){
    const res = await fetch('./api.php?action=blocked_add&ts=' + Date.now(), {
      method: 'POST',
      headers: {'Content-Type':'application/json','Cache-Control':'no-store'},
      cache: 'no-store',
      body: JSON.stringify({ date: ymd })
    });
    return res.json();
  }

  // 単日追加
  const addBtn = $('blocked-add');
  const dateInput = $('blocked-date');
  if (addBtn && dateInput){
    addBtn.addEventListener('click', async () => {
      const ymd = (dateInput.value || '').trim(); // そのまま送る（ISO）
      if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) { alert('日付はYYYY-MM-DDで入力してください'); return; }
      const r = await apiBlockedAdd(ymd);
      if (r && r.ok === false){ alert(r.error || '追加に失敗しました'); return; }
      alert('予約不可日を追加しました');
      dateInput.value = '';
    });
  }

  // 範囲追加（ここがズレ対策の肝）
  const addRangeBtn = $('blocked-add-range');
  const startInput  = $('blocked-start');
  const endInput    = $('blocked-end');
  if (addRangeBtn && startInput && endInput){
    addRangeBtn.addEventListener('click', async () => {
      const start = parseYmdToLocalDate(startInput.value);
      const end   = parseYmdToLocalDate(endInput.value);
      if (!start || !end){ alert('日付の形式が不正です'); return; }
      if (end < start){ alert('終了日は開始日以降を指定してください'); return; }

      // 両端を含む（inclusive）
      for (let d = new Date(start); d <= end; d = addDaysLocal(d, 1)){
        const ymd = formatLocalDateToYmd(d); // ローカルの"YYYY-MM-DD"
        const r = await apiBlockedAdd(ymd);
        if (r && r.ok === false){ alert(r.error || '追加に失敗しました'); return; }
      }
      alert('予約不可日を追加しました');
    });
  }
})();
