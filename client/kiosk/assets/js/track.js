const form = document.getElementById('trackForm');
const msg = document.getElementById('msg');
const result = document.getElementById('result');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.textContent = 'Checking...';
  result.hidden = true;

  const fd = new FormData(form);
  const ref = (fd.get('ref') || '').toString().trim();
  const last_name = (fd.get('last_name') || '').toString().trim();
  const mobile_no = (fd.get('mobile_no') || '').toString().trim();

  const params = new URLSearchParams({ ref });
  if (last_name) params.set('last_name', last_name);
  if (mobile_no) params.set('mobile_no', mobile_no);

  const url = `/eserbisyo-hub/api/public/requests/track.php?${params.toString()}`;

  try {
    const res  = await fetch(url);
    const text = await res.text();

    let data;
    try { data = JSON.parse(text); } catch { data = null; }

    if (!res.ok) {
      msg.textContent = data?.error || text;
      return;
    }

    document.getElementById('rRef').textContent = data.reference_no;
    document.getElementById('rName').textContent = data.name;
    document.getElementById('rSvc').textContent = data.service_code;
    document.getElementById('rStatus').textContent = data.status;
    document.getElementById('rPay').textContent = data.payment_status;

    const ul = document.getElementById('hist');
    ul.innerHTML = '';
    (data.history || []).forEach(h => {
      const li = document.createElement('li');
      li.textContent = `${h.changed_at} — ${h.new_status}${h.note ? ' ('+h.note+')' : ''}`;
      ul.appendChild(li);
    });

    msg.textContent = '';
    result.hidden = false;
  } catch (err) {
    msg.textContent = 'Network error. Please check your connection and try again.';
  }
});
