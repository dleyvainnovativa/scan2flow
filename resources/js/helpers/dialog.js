/**
 * helpers/dialog.js — themed confirm() / prompt() replacements.
 *
 * Promise-based, dynamically built Bootstrap modals that inherit the app theme
 * (dark/light). One reusable component for every destructive/confirm action.
 *
 *   const ok = await dialog.confirm({ title, message, confirmText, danger:true });
 *   if (ok) { ... }
 *
 *   const reason = await dialog.prompt({ title, label, required:true });
 *   if (reason !== null) { ... }   // null === cancelled
 *
 * Exposed on window.DM as DM.confirm / DM.prompt (see app.js), so inline Blade
 * scripts and extracted ES modules can both use it.
 */

let escId = 0;

/** Build the modal DOM, return { el, resolve-wired controls, teardown }. */
function buildModal({ title, bodyHtml, confirmText, cancelText, danger }) {
  const wrap = document.createElement('div');
  wrap.className = 'modal fade';
  wrap.tabIndex = -1;
  wrap.setAttribute('aria-hidden', 'true');
  const btnClass = danger ? 'btn-danger' : 'btn-primary';

  wrap.innerHTML = `
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius: var(--dm-radius);">
        <div class="modal-header">
          <h5 class="modal-title">${escapeText(title)}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">${bodyHtml}</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dm-cancel>${escapeText(cancelText)}</button>
          <button type="button" class="btn ${btnClass}" data-dm-confirm>${escapeText(confirmText)}</button>
        </div>
      </div>
    </div>`;

  document.body.appendChild(wrap);
  return wrap;
}

function escapeText(s) {
  const d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

/** Show a modal and resolve when the user confirms/cancels. */
function open(wrap, onConfirm) {
  return new Promise((resolve) => {
    const instance = window.bootstrap.Modal.getOrCreateInstance(wrap);
    let settled = false;

    const finish = (value) => {
      if (settled) return;
      settled = true;
      resolve(value);
      instance.hide();
    };

    wrap.querySelector('[data-dm-confirm]').addEventListener('click', () => {
      const result = onConfirm ? onConfirm(wrap) : true;
      if (result === undefined) return; // validation failed → keep open
      finish(result);
    });
    wrap.querySelector('[data-dm-cancel]').addEventListener('click', () => finish(null));

    // Backdrop / Esc / X → treat as cancel; then remove from DOM.
    wrap.addEventListener('hidden.bs.modal', () => {
      if (!settled) resolve(null);
      instance.dispose();
      wrap.remove();
    }, { once: true });

    instance.show();

    // Focus the primary control for keyboard users.
    wrap.addEventListener('shown.bs.modal', () => {
      const input = wrap.querySelector('textarea, input');
      (input || wrap.querySelector('[data-dm-confirm]'))?.focus();
    }, { once: true });
  });
}

export const dialog = {
  /**
   * Confirm dialog. Resolves true (confirmed) or false (cancelled).
   * @returns {Promise<boolean>}
   */
  async confirm({
    title = 'Confirmar',
    message = '¿Estás seguro?',
    confirmText = 'Aceptar',
    cancelText = 'Cancelar',
    danger = false,
  } = {}) {
    const wrap = buildModal({
      title,
      bodyHtml: `<p class="mb-0">${escapeText(message)}</p>`,
      confirmText, cancelText, danger,
    });
    const result = await open(wrap, () => true);
    return result === true; // null (cancel) → false
  },

  /**
   * Prompt dialog with a textarea. Resolves the string, or null if cancelled.
   * If required and left empty, shows an inline error and keeps the modal open.
   * @returns {Promise<string|null>}
   */
  async prompt({
    title = 'Escribe algo',
    label = '',
    placeholder = '',
    value = '',
    confirmText = 'Aceptar',
    cancelText = 'Cancelar',
    required = false,
    danger = false,
    multiline = true,
  } = {}) {
    const id = `dm-dlg-input-${++escId}`;
    const field = multiline
      ? `<textarea id="${id}" class="form-control" rows="3" placeholder="${escapeText(placeholder)}">${escapeText(value)}</textarea>`
      : `<input id="${id}" class="form-control" type="text" placeholder="${escapeText(placeholder)}" value="${escapeText(value)}">`;

    const bodyHtml = `
      ${label ? `<label class="form-label" for="${id}">${escapeText(label)}</label>` : ''}
      ${field}
      <div class="invalid-feedback d-block mt-1" data-dm-error style="display:none;"></div>`;

    const wrap = buildModal({ title, bodyHtml, confirmText, cancelText, danger });

    return open(wrap, (w) => {
      const input = w.querySelector(`#${id}`);
      const err = w.querySelector('[data-dm-error]');
      const val = (input.value || '').trim();
      if (required && val === '') {
        err.textContent = 'Este campo es obligatorio.';
        err.style.display = 'block';
        input.classList.add('is-invalid');
        input.focus();
        return undefined; // keep modal open
      }
      return val;
    });
  },
};
