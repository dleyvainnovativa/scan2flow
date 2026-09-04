/**
 * modules/sftp-connections.js — tenant-admin CRUD for SFTP connections.
 * Loaded when <body data-page="sftp.index">.
 *
 * Credentials are write-only: the edit form never receives stored secrets. A
 * blank secret field on edit means "keep current" (the server preserves it).
 */

import { apiPost, apiPut, apiDelete } from '../helpers/http.js';
import { toast } from '../helpers/toast.js';
import { dialog } from '../helpers/dialog.js';
import { modal } from '../helpers/dom.js';

export function init() {
  const data = document.getElementById('sftp-data');
  if (!data) return;
  const storeUrl = data.dataset.storeUrl;

  let editingUrl = null; // null = creating

  const $ = (id) => document.getElementById(id);

  // Show/hide auth blocks based on the selected auth type.
  function syncAuthBlocks() {
    const type = $('sf-auth').value;
    document.querySelectorAll('[data-auth-block]').forEach((el) => {
      el.hidden = el.dataset.authBlock !== type;
    });
  }
  $('sf-auth').addEventListener('change', syncAuthBlocks);

  function openModal({ editing = false, row = null } = {}) {
    editingUrl = editing ? row.dataset.updateUrl : null;
    $('sftp-modal-title').textContent = editing ? 'Editar conexión' : 'Nueva conexión';

    // Reset secrets always (never prefilled).
    $('sf-password').value = '';
    $('sf-key').value = '';
    $('sf-passphrase').value = '';

    // Edit hints (leave blank to keep) only show when editing.
    document.querySelectorAll('[data-edit-hint]').forEach((h) => { h.hidden = !editing; });

    if (editing) {
      const c = JSON.parse(row.dataset.json);
      $('sf-id').value = c.id;
      $('sf-name').value = c.name;
      $('sf-host').value = c.host;
      $('sf-port').value = c.port;
      $('sf-username').value = c.username;
      $('sf-auth').value = c.auth_type;
      $('sf-base').value = c.base_path;
      $('sf-processed').value = c.processed_path;
    } else {
      $('sf-id').value = '';
      $('sf-name').value = '';
      $('sf-host').value = '';
      $('sf-port').value = '22';
      $('sf-username').value = '';
      $('sf-auth').value = 'password';
      $('sf-base').value = '/';
      $('sf-processed').value = 'processed';
    }
    syncAuthBlocks();
    modal.show('#sftp-modal');
  }

  function collectPayload() {
    return {
      name:           $('sf-name').value.trim(),
      host:           $('sf-host').value.trim(),
      port:           parseInt($('sf-port').value, 10) || 22,
      username:       $('sf-username').value.trim(),
      auth_type:      $('sf-auth').value,
      password:       $('sf-password').value,      // blank on edit = keep
      private_key:    $('sf-key').value,           // blank on edit = keep
      passphrase:     $('sf-passphrase').value,
      base_path:      $('sf-base').value.trim() || '/',
      processed_path: $('sf-processed').value.trim() || 'processed',
    };
  }

  // New
  $('btn-new-sftp').addEventListener('click', () => openModal({ editing: false }));

  // Save (create or update)
  $('sf-save').addEventListener('click', async () => {
    const p = collectPayload();
    if (!p.name || !p.host || !p.username) {
      toast.error('Nombre, host y usuario son obligatorios.');
      return;
    }
    // On CREATE, require the relevant secret.
    if (!editingUrl) {
      if (p.auth_type === 'password' && !p.password) { toast.error('Ingresa la contraseña.'); return; }
      if (p.auth_type === 'key' && !p.private_key)   { toast.error('Ingresa la llave privada.'); return; }
    }

    try {
      if (editingUrl) {
        await apiPut(editingUrl, p);
        toast.success('Conexión actualizada.');
      } else {
        await apiPost(storeUrl, p);
        toast.success('Conexión creada.');
      }
      modal.hide('#sftp-modal');
      setTimeout(() => location.reload(), 600);
    } catch (err) {
      toast.error(err.data?.message || err.message || 'No se pudo guardar.');
    }
  });

  // Row actions (edit / delete / test) via delegation.
  document.getElementById('sftp-rows').addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const row = btn.closest('tr[data-id]');
    const action = btn.dataset.action;

    if (action === 'edit') {
      openModal({ editing: true, row });
      return;
    }

    if (action === 'delete') {
      const ok = await dialog.confirm({
        title: 'Eliminar conexión',
        message: '¿Eliminar esta conexión SFTP? Las plantillas que la usen dejarán de capturar.',
        confirmText: 'Eliminar',
        danger: true,
      });
      if (!ok) return;
      try {
        await apiDelete(row.dataset.destroyUrl);
        toast.success('Conexión eliminada.');
        row.remove();
      } catch (err) {
        toast.error(err.data?.message || err.message || 'No se pudo eliminar.');
      }
      return;
    }

    if (action === 'test') {
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      try {
        const r = await apiPost(row.dataset.testUrl, {});
        toast.success(`${r.message} (${r.files} archivos, ${r.dirs} carpetas)`);
      } catch (err) {
        toast.error(err.data?.message || err.message || 'Falló la conexión.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    }
  });
}
