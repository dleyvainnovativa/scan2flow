/**
 * auth.js — Firebase Authentication bridge (scaffold; fully wired in Phase 1).
 *
 * Responsibilities (Phase 1):
 *  - Initialize Firebase from window.DM_FIREBASE_CONFIG (injected by Blade).
 *  - On login, grab the ID token and hand it to http.setBearerToken(), and POST
 *    it to a Laravel endpoint that verifies it and opens a server session.
 *  - Expose signIn/signOut helpers used by the login view.
 *
 * For Phase 0 this is a safe no-op so the bundle builds and runs without
 * Firebase configured yet.
 */

import { setBearerToken } from './http.js';

export function initAuth() {
  const cfg = window.DM_FIREBASE_CONFIG;
  if (!cfg || !cfg.apiKey) {
    // Not configured yet — expected during Phase 0.
    return;
  }
  // Phase 1 will import the Firebase SDK here and set up onAuthStateChanged,
  // calling setBearerToken(idToken) whenever the token refreshes.
}

export { setBearerToken };
