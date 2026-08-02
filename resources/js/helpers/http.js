/**
 * http.js — thin fetch wrapper for JSON APIs.
 * Auto-attaches CSRF token (Blade meta) and, if present, a Firebase bearer token.
 * All methods return parsed JSON and throw an Error (with .status and .data) on failure.
 */

function csrfToken() {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el ? el.getAttribute('content') : '';
}

// Set by auth.js after Firebase login; null when unauthenticated.
let _bearer = null;
export function setBearerToken(token) { _bearer = token; }

async function request(method, url, body = null, options = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
    ...(options.headers || {}),
  };

  const isForm = body instanceof FormData;
  if (body && !isForm) headers['Content-Type'] = 'application/json';
  if (_bearer) headers['Authorization'] = `Bearer ${_bearer}`;

  const res = await fetch(url, {
    method,
    headers,
    credentials: 'same-origin',
    body: body ? (isForm ? body : JSON.stringify(body)) : null,
    ...options,
  });

  let data = null;
  const text = await res.text();
  if (text) { try { data = JSON.parse(text); } catch { data = text; } }

  if (!res.ok) {
    const err = new Error((data && data.message) || `Request failed (${res.status})`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

export const apiGet    = (url, options)       => request('GET', url, null, options);
export const apiPost   = (url, body, options) => request('POST', url, body, options);
export const apiPut    = (url, body, options) => request('PUT', url, body, options);
export const apiDelete = (url, body, options) => request('DELETE', url, body, options);
