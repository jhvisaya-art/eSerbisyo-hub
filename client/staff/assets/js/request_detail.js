import { requireLogin, doLogout, staffPost } from './auth.js';

const msgEl = document.getElementById('msg');

const refParam = new URLSearchParams(window.location.search).get('ref');
if (!refParam) {
  msgEl.textContent = "Missing ?ref= in URL";
  throw new Error("Missing ref");
}

let currentUser = null;

// ── Timezone helper ───────────────────────────────────────────────────────────
// Postgres returns timestamps in UTC without a timezone marker.
// This helper converts them to Philippine local time for display.
function formatPHDate(utcString) {
  if (!utcString) return "";
  let iso = String(utcString).includes("T") ? utcString : String(utcString).replace(" ", "T");
  if (!iso.endsWith("Z") && !/[+-]\d{2}:?\d{2}$/.test(iso)) iso += "Z";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return utcString;
  return d.toLocaleString("en-PH", {
    timeZone: "Asia/Manila",
    year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", hour12: false
  });
}

async function load() {
  msgEl.textContent = "Loading...";
  const url = `/eserbisyo-hub/api/public/staff/get_request.php?ref=${encodeURIComponent(refParam)}`;

  const res = await fetch(url, {
    cache: "no-store",
    credentials: "same-origin"
  });

  const data = await res.json().catch(() => null);

  if (!res.ok) {
    msgEl.textContent = data?.error || "Error loading request";
    return;
  }

  const r = data.request;

  document.getElementById('title').textContent = `Request ${r.reference_no}`;
  document.getElementById('ref').textContent = r.reference_no;
  document.getElementById('status').textContent = r.status;
  document.getElementById('pay').textContent = r.payment_status;
  document.getElementById('svc').textContent = r.service_code;

  document.getElementById('name').textContent =
    `${r.first_name} ${r.middle_name || ""} ${r.last_name}`.replace(/\s+/g, ' ').trim();

  document.getElementById('mobile').textContent = r.mobile_no;
  document.getElementById('addr').textContent = r.address_line;

  document.getElementById('newStatus').value = r.status;

  const hist = document.getElementById('hist');
  hist.innerHTML = '';
  (data.history || []).forEach(h => {
    const li = document.createElement('li');
    li.textContent = `${formatPHDate(h.changed_at)} — ${h.new_status} (${h.changed_by})${h.note ? ' - ' + h.note : ''}`;
    hist.appendChild(li);
  });

  const sms = document.getElementById('sms');
  sms.innerHTML = '';
  (data.sms_logs || []).forEach(s => {
    const li = document.createElement('li');
    li.textContent = `${formatPHDate(s.sent_at)} — ${s.status} to ${s.recipient_mobile}: ${s.message}`;
    sms.appendChild(li);
  });

  msgEl.textContent = "";
}

async function postJSON(url, payload) {
  return staffPost(url, payload);
}

// Print
document.getElementById('btnPrint').addEventListener('click', () => {
  window.print();
});

// Logout
document.getElementById('logoutBtn').addEventListener('click', doLogout);

// Actions
document.getElementById('btnStatus').addEventListener('click', async () => {
  try {
    msgEl.textContent = "Updating status...";
    await postJSON('/eserbisyo-hub/api/public/staff/update_status.php', {
      reference_no: refParam,
      new_status: document.getElementById('newStatus').value,
      note: document.getElementById('note').value.trim()
    });
    await load();
  } catch (e) { msgEl.textContent = e.message; }
});

document.getElementById('btnPay').addEventListener('click', async () => {
  try {
    msgEl.textContent = "Verifying payment...";
    await postJSON('/eserbisyo-hub/api/public/staff/verify_payment.php', {
      reference_no: refParam,
      or_no: document.getElementById('orNo').value.trim(),
      paid_amount: Number(document.getElementById('amt').value)
    });
    await load();
  } catch (e) { msgEl.textContent = e.message; }
});

document.getElementById('btnSms').addEventListener('click', async () => {
  try {
    msgEl.textContent = "Logging SMS...";
    await postJSON('/eserbisyo-hub/api/public/staff/send_ready_sms.php', {
      reference_no: refParam,
      message: document.getElementById('smsMsg').value.trim()
    });
    await load();
  } catch (e) { msgEl.textContent = e.message; }
});

document.getElementById('btnRelease').addEventListener('click', async () => {
  try {
    msgEl.textContent = "Marking released...";
    await postJSON('/eserbisyo-hub/api/public/staff/release.php', {
      reference_no: refParam,
      note: 'Released via staff dashboard'
    });
    await load();
  } catch (e) { msgEl.textContent = e.message; }
});

// INIT: require login + hide admin-only UI + load data
(async () => {
  const me = await requireLogin();
  currentUser = me.user;

  if (currentUser.role !== 'ADMIN') {
    document.getElementById('btnPay').style.display = 'none';
    document.getElementById('btnRelease').style.display = 'none';
    document.getElementById('orNo').style.display = 'none';
    document.getElementById('amt').style.display = 'none';
  }

  load();
})();
