/**
 * theme.js — light/dark toggle.
 * Persists to a cookie (so Blade can read it server-side and avoid a flash)
 * and localStorage. The initial theme is applied inline in <head> (see layout).
 */

const KEY = 'dm-theme';

function setCookie(value) {
  const oneYear = 60 * 60 * 24 * 365;
  document.cookie = `${KEY}=${value};path=/;max-age=${oneYear};SameSite=Lax`;
}

export function getTheme() {
  return document.documentElement.getAttribute('data-theme') || 'light';
}

export function setTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  try { localStorage.setItem(KEY, theme); } catch {}
  setCookie(theme);
  document.querySelectorAll('[data-theme-icon]').forEach((el) => {
    el.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  });
}

export function toggleTheme() {
  setTheme(getTheme() === 'dark' ? 'light' : 'dark');
}

export function initTheme() {
  // data-theme is already set by the inline head script; just wire the buttons.
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', toggleTheme);
  });
  setTheme(getTheme()); // sync icons
}
