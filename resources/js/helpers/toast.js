/**
 * toast.js — lightweight, dependency-free toast notifications.
 * Usage: toast.success('Saved'), toast.error('Something went wrong'), etc.
 */

const ICONS = {
  success: 'fa-circle-check',
  error:   'fa-circle-exclamation',
  info:    'fa-circle-info',
  warning: 'fa-triangle-exclamation',
};

function ensureWrap() {
  let wrap = document.querySelector('.dm-toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'dm-toast-wrap';
    wrap.setAttribute('aria-live', 'polite');
    wrap.setAttribute('aria-atomic', 'true');
    document.body.appendChild(wrap);
  }
  return wrap;
}

function show(message, type = 'info', timeout = 4000) {
  const wrap = ensureWrap();
  const el = document.createElement('div');
  el.className = `dm-toast dm-toast--${type}`;
  el.setAttribute('role', type === 'error' ? 'alert' : 'status');
  el.innerHTML = `<i class="fa-solid ${ICONS[type] || ICONS.info}"></i><div>${message}</div>`;
  wrap.appendChild(el);

  const remove = () => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 200);
  };
  if (timeout) setTimeout(remove, timeout);
  el.addEventListener('click', remove);
  return el;
}

export const toast = {
  success: (m, t) => show(m, 'success', t),
  error:   (m, t) => show(m, 'error', t),
  info:    (m, t) => show(m, 'info', t),
  warning: (m, t) => show(m, 'warning', t),
  show,
};
