/**
 * app.js — main entry point (compiled by Vite).
 * Imports Bootstrap JS, the global stylesheet, and wires up shell + helpers.
 *
 * Helpers are re-exported on window.DM for use in inline Blade <script> blocks
 * where a full ES module isn't warranted.
 */

import 'bootstrap';
import * as bootstrap from 'bootstrap';
import '../css/theme.css';

import { apiGet, apiPost, apiPut, apiDelete, setBearerToken } from './helpers/http.js';
import { toast } from './helpers/toast.js';
import { serializeForm, setLoading, withLoading, modal, escapeHtml } from './helpers/dom.js';
import { dialog } from './helpers/dialog.js';
import { initTheme, toggleTheme, setTheme, getTheme } from './helpers/theme.js';
import { initShell } from './helpers/ui.js';
import { initAuth } from './helpers/auth.js';

// Expose Bootstrap globally so dom.js modal helpers can find it.
window.bootstrap = bootstrap;

// Public helper surface for inline scripts.
window.DM = {
  apiGet, apiPost, apiPut, apiDelete, setBearerToken,
  toast,
  serializeForm, setLoading, withLoading, modal, escapeHtml,
  toggleTheme, setTheme, getTheme,
  confirm: dialog.confirm, prompt: dialog.prompt,
};

// --- Ready signal for inline @push('scripts') blocks ---------------------
// Vite loads this file as type="module" (deferred), so it runs AFTER inline
// page scripts have parsed. Inline scripts therefore can't rely on window.DM
// existing yet. They call window.onDM(cb); the tiny head stub (in the layout)
// queues those callbacks before this module loads, and here we flush them.
if (window.__dmReady) {
  window.__dmReady(window.DM); // flush queued callbacks + switch to immediate mode
}

document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  initShell();
  initAuth();
  initPageModule();
});

// --- Per-page module dispatch --------------------------------------------
// Views set <body data-page="documents.show"> (etc). We dynamically import the
// matching module so each page only downloads its own JS (code-splitting).
// Add a case here as you extract more views.
function initPageModule() {
  const page = document.body.dataset.page;
  if (!page) return;

  const routes = {
    'documents.show': () => import('./modules/documents-show.js'),
    'users.permissions': () => import('./modules/user-permissions.js'),
    'platform.tenants':      () => import('./modules/platform-tenants.js'),      // >>> ADD
    'platform.tenant-show':  () => import('./modules/platform-tenant-show.js'),  // >>> ADD
     'sftp.index': () => import('./modules/sftp-connections.js'),
    // 'templates.index': () => import('./modules/templates-index.js'),
    // 'areas.show':      () => import('./modules/areas-show.js'),
  };

  const loader = routes[page];
  if (!loader) return;

  loader()
    .then((m) => m.init?.())
    .catch((err) => console.error(`Failed to load page module "${page}"`, err));
}
