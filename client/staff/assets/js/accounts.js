// client/staff/assets/js/accounts.js
// Account management page — ADMIN only.
// Mirrors the Accounts module from eSerbisyo-RPI staff.js.

import { requireLogin, doLogout, staffPost } from './auth.js';
import './a11y-prefs.js';

const BASE = '/eserbisyo-hub/api/public/staff';

let allAccounts   = [];
let resetTargetId = null;
let currentUser   = null;

// ── Helpers ───────────────────────────────────────────────────────────────────
function setMsg(id, text, isErr = false) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = text;
  el.style.color = text ? (isErr ? "#b91c1c" : "#15803d") : "";
}

function pill(text, cls) {
  return `<span class="pill ${cls}">${text}</span>`;
}

async function fetchJSON(url) {
  const res  = await fetch(url, { cache: "no-store", credentials: "same-origin" });
  const data = await res.json().catch(() => null);
  if (!res.ok) throw new Error(data?.error || "Request failed");
  return data;
}

// ── Load & render accounts ────────────────────────────────────────────────────
async function loadAccounts() {
  setMsg("accListMsg", "Loading...");
  try {
    const data = await fetchJSON(`${BASE}/accounts_list.php`);
    allAccounts = data.data || [];
    renderTable(allAccounts);
    const title = document.getElementById("accTableTitle");
    if (title) title.textContent = `All Accounts (${allAccounts.length})`;
    setMsg("accListMsg", "");
  } catch (e) {
    setMsg("accListMsg", e.message, true);
  }
}

function renderTable(list) {
  const tbody = document.getElementById("accRows");
  if (!tbody) return;
  tbody.innerHTML = "";

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#888;">No accounts found.</td></tr>`;
    return;
  }

  list.forEach(a => {
    const isActive = parseInt(a.is_active) === 1;
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td><strong>${a.username}</strong></td>
      <td>${pill(a.role, a.role === "ADMIN" ? "pill-admin" : "")}</td>
      <td>${pill(isActive ? "Active" : "Inactive", isActive ? "pill-active" : "pill-inactive")}</td>
      <td style="font-size:0.8rem;color:#888;">${(a.created_at || "").slice(0, 10)}</td>
      <td class="noWrap" style="display:flex;gap:0.35rem;flex-wrap:wrap;">
        <button class="btn mini ${isActive ? "" : "success"}" type="button"
          data-acc-toggle="${a.id}">${isActive ? "Deactivate" : "Activate"}</button>
        <button class="btn mini" type="button"
          data-acc-reset="${a.id}"
          data-acc-uname="${a.username}">Reset PW</button>
        <button class="btn mini danger" type="button"
          data-acc-del="${a.id}"
          data-acc-uname="${a.username}">Delete</button>
      </td>`;
    tbody.appendChild(tr);
  });
}

// ── Create account ────────────────────────────────────────────────────────────
async function createAccount() {
  const username = document.getElementById("accNewUsername")?.value.trim();
  const password = document.getElementById("accNewPassword")?.value;
  const role     = document.getElementById("accNewRole")?.value || "STAFF";

  if (!username)                         { setMsg("accCreateMsg", "Username is required.", true); return; }
  if (!password || password.length < 8)  { setMsg("accCreateMsg", "Password must be at least 8 characters.", true); return; }

  setMsg("accCreateMsg", "Creating...");
  try {
    const data = await staffPost(`${BASE}/accounts_register.php`, { username, password, role });
    setMsg("accCreateMsg", `✓ Account "${data.username}" (${data.role}) created.`);
    document.getElementById("accNewUsername").value = "";
    document.getElementById("accNewPassword").value = "";
    document.getElementById("accNewRole").value     = "STAFF";
    await loadAccounts();
  } catch (e) {
    setMsg("accCreateMsg", e.message, true);
  }
}

// ── Reset password modal ──────────────────────────────────────────────────────
function openResetModal(id, username) {
  resetTargetId = id;
  const nameEl = document.getElementById("accResetUsername");
  const pwEl   = document.getElementById("accResetPwInput");
  const msgEl  = document.getElementById("accResetMsg");
  if (nameEl) nameEl.textContent = username;
  if (pwEl)   pwEl.value         = "";
  if (msgEl)  msgEl.textContent  = "";

  const overlay = document.getElementById("accResetOverlay");
  if (overlay) { overlay.hidden = false; overlay.style.display = "flex"; }
  pwEl?.focus();
}

function closeResetModal() {
  resetTargetId = null;
  const overlay = document.getElementById("accResetOverlay");
  if (overlay) { overlay.hidden = true; overlay.style.display = "none"; }
}

async function confirmReset() {
  const pw = document.getElementById("accResetPwInput")?.value;
  if (!pw || pw.length < 8) { setMsg("accResetMsg", "Password must be at least 8 characters.", true); return; }
  setMsg("accResetMsg", "Resetting...");
  try {
    await staffPost(`${BASE}/accounts_reset_password.php`, { id: resetTargetId, new_password: pw });
    setMsg("accResetMsg", "✓ Password reset successfully.");
    setTimeout(closeResetModal, 1200);
  } catch (e) {
    setMsg("accResetMsg", e.message, true);
  }
}

// ── Event bindings ────────────────────────────────────────────────────────────
document.getElementById("logoutBtn")?.addEventListener("click", doLogout);
document.getElementById("btnAccCreate")?.addEventListener("click", createAccount);
document.getElementById("btnAccRefresh")?.addEventListener("click", loadAccounts);

document.getElementById("accSearch")?.addEventListener("input", e => {
  const q = e.target.value.trim().toLowerCase();
  renderTable(q ? allAccounts.filter(a => a.username.toLowerCase().includes(q)) : allAccounts);
});

// Table row actions (delegated)
document.getElementById("accRows")?.addEventListener("click", async e => {
  const toggleBtn = e.target.closest("[data-acc-toggle]");
  const resetBtn  = e.target.closest("[data-acc-reset]");
  const delBtn    = e.target.closest("[data-acc-del]");

  if (toggleBtn) {
    setMsg("accListMsg", "Updating...");
    try {
      const d = await staffPost(`${BASE}/accounts_toggle.php`, { id: parseInt(toggleBtn.dataset.accToggle) });
      setMsg("accListMsg", `✓ ${d.message}`);
      await loadAccounts();
    } catch (e) { setMsg("accListMsg", e.message, true); }
  }

  if (resetBtn) {
    openResetModal(parseInt(resetBtn.dataset.accReset), resetBtn.dataset.accUname);
  }

  if (delBtn) {
    const uname = delBtn.dataset.accUname;
    if (!confirm(`Permanently delete account "${uname}"?\n\nThis cannot be undone.`)) return;
    setMsg("accListMsg", "Deleting...");
    try {
      const d = await staffPost(`${BASE}/accounts_delete.php`, { id: parseInt(delBtn.dataset.accDel) });
      setMsg("accListMsg", `✓ ${d.message}`);
      await loadAccounts();
    } catch (e) { setMsg("accListMsg", e.message, true); }
  }
});

// Reset modal
document.getElementById("accResetClose")?.addEventListener("click", closeResetModal);
document.getElementById("accResetOverlay")?.addEventListener("click", e => {
  if (e.target === document.getElementById("accResetOverlay")) closeResetModal();
});
document.getElementById("btnAccConfirmReset")?.addEventListener("click", confirmReset);
document.addEventListener("keydown", e => {
  if (e.key === "Escape" && !document.getElementById("accResetOverlay")?.hidden) closeResetModal();
});

// ── INIT ─────────────────────────────────────────────────────────────────────
(async () => {
  const me = await requireLogin();
  currentUser = me.user;

  // Redirect non-admins away immediately
  if (currentUser.role !== "ADMIN") {
    window.location.href = "./dashboard.html";
    return;
  }

  await loadAccounts();
})();