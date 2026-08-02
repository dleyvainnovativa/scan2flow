/**
 * dom.js — small DOM utilities: form serialization, loading states, modal helpers.
 */

/** Serialize a <form> (or any container with named inputs) into a plain object.
 *  Handles checkboxes, multi-selects, and multiple same-name fields (-> array). */
export function serializeForm(formEl) {
  const out = {};
  const fd = new FormData(formEl);

  // Assign a value into `out`, expanding bracket notation:
  //   "metadata[folio]"  -> out.metadata.folio
  //   "tags[]"           -> out.tags = [...]
  //   "a[b][c]"          -> out.a.b.c
  const assign = (rawKey, value) => {
    const match = rawKey.match(/^([^\[]+)((?:\[[^\]]*\])+)$/);
    if (!match) {
      // Plain key (no brackets) — keep the original repeat-into-array behavior.
      if (rawKey in out) {
        out[rawKey] = Array.isArray(out[rawKey]) ? [...out[rawKey], value] : [out[rawKey], value];
      } else {
        out[rawKey] = value;
      }
      return;
    }
    const root = match[1];
    const segments = [...match[2].matchAll(/\[([^\]]*)\]/g)].map((m) => m[1]);
    let node = out;
    let key = root;
    for (let i = 0; i < segments.length; i++) {
      const seg = segments[i];
      if (typeof node[key] !== 'object' || node[key] === null) node[key] = {};
      node = node[key];
      // Empty segment [] means push to an array.
      key = seg === '' ? node.length ?? 0 : seg;
    }
    node[key] = value;
  };

  for (const [key, value] of fd.entries()) {
    assign(key, value);
  }

  // Unchecked checkboxes are absent from FormData; normalize them to false.
  formEl.querySelectorAll('input[type="checkbox"][name]').forEach((cb) => {
    if (cb.checked) return; // checked ones already handled above
    // Only set false for plain-named checkboxes; bracketed ones are rare here.
    if (!cb.name.includes('[') && !(cb.name in out)) out[cb.name] = false;
  });

  return out;
}

/** Toggle a loading state on any element (dims + disables). */
export function setLoading(el, isLoading = true) {
  if (!el) return;
  el.classList.toggle('dm-loading', isLoading);
  if ('disabled' in el) el.disabled = isLoading;
}

/** Wrap a button click in a loading state while an async fn runs. */
export async function withLoading(el, asyncFn) {
  const original = el ? el.innerHTML : null;
  try {
    setLoading(el, true);
    if (el) el.innerHTML = '<span class="dm-spinner"></span>';
    return await asyncFn();
  } finally {
    setLoading(el, false);
    if (el && original !== null) el.innerHTML = original;
  }
}

/** Thin wrappers over Bootstrap's Modal (loaded globally as bootstrap.Modal). */
export const modal = {
  show(selector) {
    const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (el && window.bootstrap) return window.bootstrap.Modal.getOrCreateInstance(el).show();
  },
  hide(selector) {
    const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (el && window.bootstrap) {
      const inst = window.bootstrap.Modal.getInstance(el);
      if (inst) inst.hide();
    }
  },
};

/** Escape user text before injecting into innerHTML. */
export function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}
