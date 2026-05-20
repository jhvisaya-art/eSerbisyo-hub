const form = document.getElementById('loginForm');
const msg = document.getElementById('msg');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.textContent = "Logging in...";

  const fd = new FormData(form);
  const payload = {
    username: fd.get('username'),
    password: fd.get('password')
  };

  try {
    const res = await fetch('/eserbisyo-hub/api/public/staff/login.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => null);

    if (!res.ok || !data?.ok) {
      msg.textContent = data?.error || "Login failed. Please try again.";
      return;
    }

    window.location.href = "./dashboard.html";
  } catch (_) {
    msg.textContent = "Unable to reach the login server.";
  }
});
