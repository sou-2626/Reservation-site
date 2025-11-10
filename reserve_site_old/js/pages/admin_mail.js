// js/pages/admin_mail.js
import { resolveApiBase, apiURL } from "../core/apiBase.js";
import { guardAdmin } from "../core/authGuard.js";

guardAdmin();

const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const addBtn = document.getElementById("add-btn");
const tableBody = document.querySelector("#staff-table tbody");
const status = document.getElementById("status");

// ページ読み込み時に一覧を取得
document.addEventListener("DOMContentLoaded", loadList);

// ===== 一覧表示 =====
async function loadList() {
  try {
    await resolveApiBase();
    const res = await fetch(apiURL("action=mail_list"), { cache: "no-store" });
    if (!res.ok) throw new Error("一覧取得失敗");
    const data = await res.json();
    renderTable(data);
  } catch (e) {
    console.error(e);
    status.textContent = "読み込みエラー：" + e.message;
  }
}

// ===== 登録処理 =====
addBtn.addEventListener("click", async () => {
  const name = nameInput.value.trim();
  const email = emailInput.value.trim();
  if (!name || !email) {
    alert("名前とメールアドレスを入力してください。");
    return;
  }

  try {
    await resolveApiBase();
    const res = await fetch(apiURL("action=mail_add"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email })
    });
    if (!res.ok) throw new Error("登録失敗");
    const result = await res.json();
    status.textContent = result.message || "登録しました。";
    nameInput.value = "";
    emailInput.value = "";
    loadList();
  } catch (e) {
    status.textContent = "エラー：" + e.message;
  }
});

// ===== 削除処理 =====
async function deleteMail(email) {
  if (!confirm("削除しますか？")) return;
  try {
    await resolveApiBase();
    const res = await fetch(apiURL("action=mail_delete"), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email })
    });
    if (!res.ok) throw new Error("削除失敗");
    const result = await res.json();
    status.textContent = result.message || "削除しました。";
    loadList();
  } catch (e) {
    status.textContent = "エラー：" + e.message;
  }
}

// ===== 表描画 =====
function renderTable(list) {
  tableBody.innerHTML = "";
  if (!Array.isArray(list) || list.length === 0) {
    tableBody.innerHTML = `<tr><td colspan="3">登録データがありません</td></tr>`;
    return;
  }

  list.forEach(({ name, email }) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${name}</td>
      <td>${email}</td>
      <td><button data-email="${email}">削除</button></td>
    `;
    tr.querySelector("button").addEventListener("click", () => deleteMail(email));
    tableBody.appendChild(tr);
  });
}
