// client/staff/assets/js/dashboard.js
// Strategy 1 — Pagination   (PAGE_SIZE rows, Prev/Next)
// Strategy 2 — Soft archive (ADMIN: archive/unarchive Released records)
// Strategy 3 — Date filter  (From/To date inputs on created_at)
// Export to Excel via SheetJS (month/year selector)
// Role-based UI: ADMIN sees Manage Accounts nav + archive + confirm payment + release

import { requireLogin, doLogout, staffPost } from './auth.js';
import './a11y-prefs.js';

const PAGE_SIZE = 20;

let currentUser         = null;
let currentFilterStatus = "";
let currentRef          = null;
let currentPage         = 1;
let totalPages          = 1;

// ── Timezone helper ───────────────────────────────────────────────────────────
// Postgres returns timestamps in UTC without a timezone marker.
// This helper converts them to Philippine local time for display.
function formatPHDate(utcString) {
  if (!utcString) return "";
  // Normalize: convert "2026-05-20 11:11:09" → "2026-05-20T11:11:09Z"
  let iso = String(utcString).includes("T") ? utcString : String(utcString).replace(" ", "T");
  if (!iso.endsWith("Z") && !/[+-]\d{2}:?\d{2}$/.test(iso)) iso += "Z";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return utcString; // fallback if parse fails
  return d.toLocaleString("en-PH", {
    timeZone: "Asia/Manila",
    year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", hour12: false
  });
}

// ── DOM refs ──────────────────────────────────────────────────────────────────
const rowsEl       = document.getElementById("rows");
const msgEl        = document.getElementById("msg");
const searchEl     = document.getElementById("search");
const modalOverlay = document.getElementById("modalOverlay");
const modalBadge   = document.getElementById("modalBadge");
const actionMsg    = document.getElementById("actionMsg");

const actionBlocks = {
  queued:    document.getElementById("action-queued"),
  review:    document.getElementById("action-review"),
  payment:   document.getElementById("action-payment"),
  inprocess: document.getElementById("action-inprocess"),
  ready:     document.getElementById("action-ready"),
  released:  document.getElementById("action-released"),
};

const payOrNo   = document.getElementById("payOrNo");
const payAmount = document.getElementById("payAmount");

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusBadgeMeta(status) {
  const s = (status || "").toLowerCase();
  if (s === "queued")                   return { text: "Queued",               cls: "badge badge-queued"    };
  if (s === "under review")             return { text: "Under Review",         cls: "badge badge-review"    };
  if (s === "for payment verification") return { text: "Payment Verification", cls: "badge badge-payment"   };
  if (s === "in process")               return { text: "In Process",           cls: "badge badge-inprocess" };
  if (s === "ready for release")        return { text: "Ready for Release",    cls: "badge badge-ready"     };
  if (s === "released")                 return { text: "Released",             cls: "badge badge-released"  };
  return { text: status || "Unknown",   cls: "badge badge-queued" };
}

function statusBadgeHTML(status) {
  const m = statusBadgeMeta(status);
  return `<span class="${m.cls}">${m.text}</span>`;
}

function paymentBadgeHTML(status) {
  const text = status || "Unpaid";
  return `<span class="pill ${text.toLowerCase() === "paid" ? "pill-paid" : ""}">${text}</span>`;
}

function renderHistory(history) {
  if (!history || history.length === 0) return "No history yet";
  return history.slice(0, 20).map(h => {
    const note = h.note ? ` — ${h.note}` : "";
    return `${formatPHDate(h.changed_at)} · ${h.new_status}${note}`;
  }).join("\n");
}

async function fetchJSON(url) {
  const res  = await fetch(url, { cache: "no-store", credentials: "same-origin" });
  const data = await res.json().catch(() => null);
  if (!res.ok) throw new Error(data?.error || "Request failed");
  return data;
}

async function postJSON(url, payload) {
  return staffPost(url, payload);
}

// ── Strategy 1: Pagination ────────────────────────────────────────────────────
function updatePagination(total) {
  totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
  const info    = document.getElementById("pageInfo");
  const btnPrev = document.getElementById("btnPrevPage");
  const btnNext = document.getElementById("btnNextPage");
  if (info)    info.textContent = `Page ${currentPage} of ${totalPages}`;
  if (btnPrev) btnPrev.disabled = currentPage <= 1;
  if (btnNext) btnNext.disabled = currentPage >= totalPages;
}

// ── Strategy 2: Archive block ─────────────────────────────────────────────────
function updateArchiveBlock(request) {
  const block      = document.getElementById("archiveBlock");
  const btn        = document.getElementById("btnArchiveToggle");
  const archiveMsg = document.getElementById("archiveMsg");
  if (!block || !btn) return;

  const isAdmin    = currentUser?.role === "ADMIN";
  const isReleased = (request.status || "").toLowerCase() === "released";

  block.hidden = !(isAdmin && isReleased);
  if (!block.hidden) {
    const archived  = parseInt(request.is_archived) === 1;
    btn.textContent = archived ? "Unarchive this record" : "Archive this record";
    btn.className   = archived ? "btn success" : "btn ghost";
    if (archiveMsg) archiveMsg.textContent = "";
  }
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal() {
  modalOverlay.hidden = false;
  modalOverlay.style.display = "flex";
  modalOverlay.style.pointerEvents = "auto";
  actionMsg.textContent = "";
  document.getElementById("modalCloseBtn")?.focus();
}

function closeModal() {
  modalOverlay.hidden = true;
  modalOverlay.style.display = "none";
  modalOverlay.style.pointerEvents = "none";
  currentRef = null;
}

function showActionForStatus(status) {
  Object.values(actionBlocks).forEach(el => { if (el) el.hidden = true; });
  actionMsg.textContent = "";
  const isAdmin = currentUser?.role === "ADMIN";
  const key     = (status || "").toLowerCase();

  if (key === "queued")                        { actionBlocks.queued.hidden = false; }
  else if (key === "under review")             { actionBlocks.review.hidden = false; }
  else if (key === "for payment verification") {
    actionBlocks.payment.hidden = false;
    document.getElementById("btnConfirmPayment").disabled = !isAdmin;
    if (!isAdmin) { actionMsg.textContent = "Only admins can confirm payments."; actionMsg.className = "inlineMsg err"; }
  }
  else if (key === "in process")               { actionBlocks.inprocess.hidden = false; }
  else if (key === "ready for release")        {
    actionBlocks.ready.hidden = false;
    document.getElementById("btnRelease").disabled = !isAdmin;
    if (!isAdmin) { actionMsg.textContent = "Only admins can release documents."; actionMsg.className = "inlineMsg err"; }
  }
  else if (key === "released")                 { actionBlocks.released.hidden = false; }
  else                                         { actionBlocks.queued.hidden = false; }
}

async function loadModal(ref) {
  currentRef = ref;
  openModal();
  try {
    const data = await fetchJSON(`/eserbisyo-hub/api/public/staff/get_request.php?ref=${encodeURIComponent(ref)}`);
    const r = data.request;

    document.getElementById("modalRef").textContent  = r.reference_no;
    document.getElementById("mName").textContent     = [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(" ").replace(/\s+/g, " ").trim();
    document.getElementById("mService").textContent  = r.service_code;
    document.getElementById("mMobile").textContent   = r.mobile_no;
    document.getElementById("mDate").textContent     = formatPHDate(r.created_at);
    document.getElementById("mAddress").textContent  = r.address_line || "—";
    document.getElementById("mHistory").textContent  = renderHistory(data.history);

    const meta = statusBadgeMeta(r.status);
    modalBadge.className   = meta.cls;
    modalBadge.textContent = meta.text;

    if (data.latest_payment) {
      if (payOrNo)   payOrNo.value   = data.latest_payment.or_no      || "";
      if (payAmount) payAmount.value = data.latest_payment.paid_amount || "";
    } else {
      if (payOrNo)   payOrNo.value   = "";
      if (payAmount) payAmount.value = "";
    }

    showActionForStatus(r.status);
    updateArchiveBlock(r);
  } catch (e) {
    actionMsg.textContent = e.message;
    actionMsg.className   = "inlineMsg err";
  }
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function loadStats() {
  try {
    const data = await fetchJSON('/eserbisyo-hub/api/public/staff/stats.php');
    const map  = {};
    (data.data || []).forEach(r => { map[r.status] = Number(r.total); });
    const cards = [
      { label: "Queued",               val: map["Queued"]                   || 0, cls: "blue"   },
      { label: "Under Review",          val: map["Under Review"]             || 0, cls: "yellow" },
      { label: "Payment Verification",  val: map["For Payment Verification"] || 0, cls: "purple" },
      { label: "In Process",            val: map["In Process"]               || 0, cls: "green"  },
      { label: "Ready for Release",     val: map["Ready for Release"]        || 0, cls: "blue"   },
      { label: "Released",              val: map["Released"]                 || 0, cls: "yellow" },
    ];
    document.getElementById("kpis").innerHTML = cards.map(c =>
      `<div class="statCard ${c.cls}"><div class="statNum">${c.val}</div><div class="statLbl">${c.label}</div></div>`
    ).join("");
  } catch (_) { /* silent */ }
}

// ── List (all three strategies) ───────────────────────────────────────────────
async function loadList() {
  msgEl.textContent = "Loading...";
  rowsEl.innerHTML  = "";

  const params = new URLSearchParams();
  if (currentFilterStatus) params.set("status", currentFilterStatus);

  const q = searchEl.value.trim();
  if (q) params.set("search", q);

  // Strategy 3 — date range
  const dateFrom = document.getElementById("dateFrom")?.value;
  const dateTo   = document.getElementById("dateTo")?.value;
  if (dateFrom) params.set("date_from", dateFrom);
  if (dateTo)   params.set("date_to",   dateTo);

  // Strategy 2 — show archived (ADMIN only)
  if (document.getElementById("showArchived")?.checked) params.set("show_archived", "1");

  // Strategy 1 — pagination
  params.set("limit",  String(PAGE_SIZE));
  params.set("offset", String((currentPage - 1) * PAGE_SIZE));

  try {
    const res  = await fetch(`/eserbisyo-hub/api/public/staff/list_requests.php?${params}`,
      { cache: "no-store", credentials: "same-origin" });
    const data = await res.json().catch(() => null);

    if (!res.ok) { msgEl.textContent = data?.error || "Error loading list"; return; }

    const list  = data.data  || [];
    const total = data.total ?? list.length;

    updatePagination(total);

    if (!list.length) { msgEl.textContent = "No records found."; updatePagination(0); return; }
    msgEl.textContent = "";

    list.forEach(r => {
      const tr       = document.createElement("tr");
      const fullName = [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(" ").replace(/\s+/g, " ").trim();
      const archived = parseInt(r.is_archived) === 1;
      tr.style.opacity = archived ? "0.55" : "";
      tr.innerHTML = `
        <td><strong>${r.reference_no}</strong>${archived ? ' <span class="pill" style="font-size:0.7rem;color:#888;">Archived</span>' : ""}</td>
        <td>${fullName}</td>
        <td>${r.service_code}</td>
        <td>${formatPHDate(r.created_at)}</td>
        <td>${statusBadgeHTML(r.status)}</td>
        <td>${paymentBadgeHTML(r.payment_status)}</td>
        <td class="noWrap"><button type="button" class="btn mini" data-open-ref="${r.reference_no}">Open</button></td>`;
      rowsEl.appendChild(tr);
    });
  } catch (e) {
    msgEl.textContent = e.message;
  }
}

// ── Export Released records to Excel ─────────────────────────────────────────
async function exportReleased() {
  const btn   = document.getElementById("btnExport");
  const month = document.getElementById("exportMonth")?.value || "";
  const year  = document.getElementById("exportYear")?.value  || "";

  if (!month || !year) { alert("Please select a month and year before exporting."); return; }

  const MONTH_NAMES = ["","January","February","March","April","May","June",
                       "July","August","September","October","November","December"];
  const monthLabel  = MONTH_NAMES[parseInt(month, 10)] || month;

  btn.textContent = "Loading...";
  btn.disabled    = true;

  try {
    // Fetch all Released records (archived included for complete records)
    const res  = await fetch(
      `/eserbisyo-hub/api/public/staff/list_requests.php?status=Released&limit=1000&show_archived=1`,
      { cache: "no-store", credentials: "same-origin" }
    );
    const data = await res.json().catch(() => null);
    const all  = data?.data || [];

    // Filter to selected month/year client-side
    // Parse the timestamp as UTC, then check its month/year in Philippine time.
    const records = all.filter(r => {
      let iso = String(r.created_at).includes("T") ? r.created_at : String(r.created_at).replace(" ", "T");
      if (!iso.endsWith("Z") && !/[+-]\d{2}:?\d{2}$/.test(iso)) iso += "Z";
      const d = new Date(iso);
      // Get PH-local month/year via Intl
      const parts = new Intl.DateTimeFormat("en-PH", {
        timeZone: "Asia/Manila", year: "numeric", month: "2-digit"
      }).formatToParts(d);
      const m = parts.find(p => p.type === "month")?.value;
      const y = parts.find(p => p.type === "year")?.value;
      return m === month && y === String(year);
    });

    if (!records.length) { alert(`No Released records found for ${monthLabel} ${year}.`); return; }

    const XLSX = window.XLSX;
    if (!XLSX) { alert("Excel library not loaded. Check your internet connection."); return; }

    const SERVICE_LABELS = {
      CEDULA: "Community Tax Certificate (Cedula)", MAYOR_CLEARANCE: "Mayor's Clearance",
      CTC_BIRTH: "Certified True Copy", DELAYED_REG: "Delayed Registration",
      BUSINESS_RENEWAL: "Business Permit Renewal", OTHER_RENEWAL: "Other Renewal Requests",
    };

    const wb = XLSX.utils.book_new();

    // Sheet 1 — Records
    const rows = records.map((r, i) => ({
      "No.": i + 1, "Reference No.": r.reference_no,
      "Last Name": r.last_name, "First Name": r.first_name, "Middle Name": r.middle_name || "",
      "Service": SERVICE_LABELS[r.service_code] || r.service_code,
      "Mobile No.": r.mobile_no, "Payment Status": r.payment_status,
      "Date Filed": formatPHDate(r.created_at), "Status": r.status,
    }));
    const wsRecords = XLSX.utils.json_to_sheet(rows);
    wsRecords["!cols"] = [
      { wch: 5 }, { wch: 22 }, { wch: 18 }, { wch: 18 }, { wch: 15 },
      { wch: 34 }, { wch: 14 }, { wch: 14 }, { wch: 20 }, { wch: 12 },
    ];
    XLSX.utils.book_append_sheet(wb, wsRecords, "Released Records");

    // Sheet 2 — Summary
    const svcCounts = {};
    records.forEach(r => {
      const label = SERVICE_LABELS[r.service_code] || r.service_code;
      svcCounts[label] = (svcCounts[label] || 0) + 1;
    });
    const paidCount = records.filter(r => r.payment_status === "Paid").length;

    const wsSummary = XLSX.utils.aoa_to_sheet([
      ["eSerbisyo Hub — Monthly Released Records Summary"],
      ["Municipality of San Fernando, Camarines Sur"],
      [`Period: ${monthLabel} ${year}`],
      [`Generated: ${new Date().toLocaleString("en-PH", { timeZone: "Asia/Manila" })}`],
      [], ["OVERVIEW"],
      ["Total Released", records.length], ["Paid", paidCount], ["Unpaid", records.length - paidCount],
      [], ["BREAKDOWN BY SERVICE"], ["Service", "Count"],
      ...Object.entries(svcCounts).map(([s, c]) => [s, c]),
    ]);
    wsSummary["!cols"] = [{ wch: 38 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, wsSummary, "Summary");

    XLSX.writeFile(wb, `eSerbisyo_Released_${year}-${month}.xlsx`);
  } catch (e) {
    alert("Export failed: " + e.message);
  } finally {
    btn.textContent = "↓ Export Excel";
    btn.disabled    = false;
  }
}

// ── Event wiring ──────────────────────────────────────────────────────────────
// Modal close
document.getElementById("modalCloseBtn")?.addEventListener("click", closeModal);
document.getElementById("btnCloseModal")?.addEventListener("click", closeModal);
modalOverlay.addEventListener("click", e => { if (e.target === modalOverlay) closeModal(); });
document.addEventListener("keydown", e => { if (!modalOverlay.hidden && e.key === "Escape") closeModal(); });

// Logout
document.getElementById("logoutBtn").addEventListener("click", doLogout);

// Nav tabs — reset page on status change
document.querySelectorAll(".navItem[data-status]").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".navItem").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    currentFilterStatus = btn.dataset.status || "";
    currentPage = 1;
    document.getElementById("panelTitle").textContent = currentFilterStatus || "All Requests";
    loadList();
  });
});

// Refresh + search
document.getElementById("refresh").addEventListener("click", () => { currentPage = 1; loadStats(); loadList(); });
searchEl.addEventListener("keydown", e => { if (e.key === "Enter") { currentPage = 1; loadList(); } });

// Strategy 3 — date filter
document.getElementById("btnApplyFilter")?.addEventListener("click", () => { currentPage = 1; loadList(); });
document.getElementById("btnClearFilter")?.addEventListener("click", () => {
  const df = document.getElementById("dateFrom");
  const dt = document.getElementById("dateTo");
  if (df) df.value = "";
  if (dt) dt.value = "";
  currentPage = 1; loadList();
});

// Strategy 2 — show archived toggle
document.getElementById("showArchived")?.addEventListener("change", () => { currentPage = 1; loadList(); });

// Strategy 1 — Prev / Next
document.getElementById("btnPrevPage")?.addEventListener("click", () => { if (currentPage > 1) { currentPage--; loadList(); } });
document.getElementById("btnNextPage")?.addEventListener("click", () => { if (currentPage < totalPages) { currentPage++; loadList(); } });

// Table row → open modal
rowsEl.addEventListener("click", e => {
  const btn = e.target.closest("[data-open-ref]");
  if (btn) loadModal(btn.dataset.openRef);
});

// Export
document.getElementById("btnExport")?.addEventListener("click", exportReleased);

// ── Action buttons — actor fields removed from payloads (server reads session) ─
document.getElementById("btnStartReview")?.addEventListener("click", async () => {
  if (!currentRef) return;
  try {
    actionMsg.textContent = "Moving to Under Review..."; actionMsg.className = "inlineMsg";
    await postJSON('/eserbisyo-hub/api/public/staff/update_status.php', {
      reference_no: currentRef, new_status: "Under Review", note: "Review started"
    });
    await loadModal(currentRef); await loadStats(); await loadList();
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

document.getElementById("btnSendPayment")?.addEventListener("click", async () => {
  if (!currentRef) return;
  try {
    actionMsg.textContent = "Sending for payment verification..."; actionMsg.className = "inlineMsg";
    await postJSON('/eserbisyo-hub/api/public/staff/update_status.php', {
      reference_no: currentRef, new_status: "For Payment Verification", note: "Sent for payment verification"
    });
    await loadModal(currentRef); await loadStats(); await loadList();
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

document.getElementById("btnConfirmPayment")?.addEventListener("click", async () => {
  if (!currentRef) return;
  if (currentUser?.role !== "ADMIN") { actionMsg.textContent = "Only admins can confirm payments."; actionMsg.className = "inlineMsg err"; return; }
  const orNo       = (payOrNo?.value || "").trim();
  const paidAmount = Number(payAmount?.value);
  if (!Number.isFinite(paidAmount) || paidAmount <= 0) { actionMsg.textContent = "Enter a valid amount."; actionMsg.className = "inlineMsg err"; return; }
  try {
    actionMsg.textContent = "Confirming payment..."; actionMsg.className = "inlineMsg";
    await postJSON('/eserbisyo-hub/api/public/staff/verify_payment.php', {
      reference_no: currentRef, or_no: orNo || undefined, paid_amount: paidAmount
    });
    await postJSON('/eserbisyo-hub/api/public/staff/update_status.php', {
      reference_no: currentRef, new_status: "In Process", note: "Payment verified"
    });
    await loadModal(currentRef); await loadStats(); await loadList();
    actionMsg.textContent = "Payment confirmed and moved to In Process."; actionMsg.className = "inlineMsg ok";
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

document.getElementById("btnMarkReady")?.addEventListener("click", async () => {
  if (!currentRef) return;
  try {
    actionMsg.textContent = "Sending readiness SMS..."; actionMsg.className = "inlineMsg";
    await postJSON('/eserbisyo-hub/api/public/staff/send_ready_sms.php', { reference_no: currentRef });
    await loadModal(currentRef); await loadStats(); await loadList();
    actionMsg.textContent = "Marked Ready for Release and SMS logged."; actionMsg.className = "inlineMsg ok";
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

document.getElementById("btnRelease")?.addEventListener("click", async () => {
  if (!currentRef) return;
  if (currentUser?.role !== "ADMIN") { actionMsg.textContent = "Only admins can release documents."; actionMsg.className = "inlineMsg err"; return; }
  try {
    actionMsg.textContent = "Releasing..."; actionMsg.className = "inlineMsg";
    await postJSON('/eserbisyo-hub/api/public/staff/release.php', {
      reference_no: currentRef, note: "Released via dashboard"
    });
    closeModal(); await loadStats(); await loadList();
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

document.getElementById("btnAddNote")?.addEventListener("click", async () => {
  if (!currentRef) return;
  const note = document.getElementById("mNote")?.value.trim();
  if (!note) return;
  try {
    actionMsg.textContent = "Saving note..."; actionMsg.className = "inlineMsg";
    const data = await fetchJSON(`/eserbisyo-hub/api/public/staff/get_request.php?ref=${encodeURIComponent(currentRef)}`);
    await postJSON('/eserbisyo-hub/api/public/staff/update_status.php', {
      reference_no: currentRef, new_status: data.request.status, note
    });
    document.getElementById("mNote").value = "";
    await loadModal(currentRef);
  } catch (e) { actionMsg.textContent = e.message; actionMsg.className = "inlineMsg err"; }
});

// Strategy 2 — archive toggle (ADMIN only)
document.getElementById("btnArchiveToggle")?.addEventListener("click", async () => {
  if (!currentRef || currentUser?.role !== "ADMIN") return;
  const archiveMsg = document.getElementById("archiveMsg");
  try {
    if (archiveMsg) { archiveMsg.textContent = "Saving..."; archiveMsg.className = "inlineMsg"; }
    await postJSON('/eserbisyo-hub/api/public/staff/archive_toggle.php', { reference_no: currentRef });
    await loadModal(currentRef);
    await loadList();
    if (archiveMsg) { archiveMsg.textContent = "Done."; archiveMsg.className = "inlineMsg ok"; }
  } catch (e) {
    if (archiveMsg) { archiveMsg.textContent = e.message; archiveMsg.className = "inlineMsg err"; }
  }
});

// ── INIT ─────────────────────────────────────────────────────────────────────
(async () => {
  const me = await requireLogin();
  currentUser = me.user;

  // Default export controls to current month/year
  const now = new Date();
  const monthEl = document.getElementById("exportMonth");
  const yearEl  = document.getElementById("exportYear");
  if (monthEl) monthEl.value = String(now.getMonth() + 1).padStart(2, "0");
  if (yearEl)  yearEl.value  = String(now.getFullYear());

  // ADMIN-only UI
  if (currentUser.role === "ADMIN") {
    const navBtn  = document.getElementById("navAccounts");
    const divider = document.getElementById("adminNavDivider");
    if (navBtn)  navBtn.style.display  = "block";
    if (divider) divider.style.display = "block";

    // Show "Show archived" toggle
    const archiveToggleWrap = document.getElementById("archiveToggleWrap");
    if (archiveToggleWrap) archiveToggleWrap.style.display = "flex";
  }

  closeModal();
  await loadStats();
  await loadList();
})();
