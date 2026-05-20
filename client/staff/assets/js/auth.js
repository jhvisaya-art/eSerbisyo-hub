let csrfToken = "";
let sessionData = null;

export async function requireLogin() {
  if (sessionData) return sessionData;

  const res = await fetch('/eserbisyo-hub/api/public/staff/me.php', {
    cache: 'no-store',
    credentials: 'same-origin'
  });

  if (!res.ok) {
    window.location.href = './login.html';
    throw new Error('Not logged in');
  }

  const data = await res.json();
  csrfToken = data.csrf_token || "";
  sessionData = data;
  return data; // { ok:true, user:{...}, csrf_token }
}

export function getCsrfToken() {
  return csrfToken;
}

export async function staffPost(url, payload) {
  if (!csrfToken) {
    await requireLogin();
  }

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  });

  const data = await res.json().catch(() => null);
  if (!res.ok) throw new Error(data?.error || "Request failed");
  return data;
}

export async function doLogout() {
  try {
    await staffPost('/eserbisyo-hub/api/public/staff/logout.php', {});
  } catch (_) {
    // even if CSRF missing or session expired, continue to redirect
  }
  window.location.href = './login.html';
}
