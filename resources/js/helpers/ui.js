/**
 * ui.js — shell interactions: mobile sidebar drawer, active-link handling.
 */

export function initShell() {
  const sidebar = document.querySelector('.dm-sidebar');
  const backdrop = document.querySelector('.dm-sidebar-backdrop');
  const burger = document.querySelector('.dm-burger');

  const open = () => { sidebar?.classList.add('is-open'); backdrop?.classList.add('is-open'); };
  const close = () => { sidebar?.classList.remove('is-open'); backdrop?.classList.remove('is-open'); };

  burger?.addEventListener('click', () => {
    sidebar?.classList.contains('is-open') ? close() : open();
  });
  backdrop?.addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
}
