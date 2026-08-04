/**
 * modules/platform-tenants.js — tenant list page (create tenant).
 * Loaded via app.js dispatcher when <body data-page="platform.tenants">.
 */

import { apiPost } from '../helpers/http.js';
import { toast } from '../helpers/toast.js';
import { modal } from '../helpers/dom.js';

export function init() {
  const data = document.getElementById('platform-tenants-data');
  if (!data) return;
  const storeUrl = data.dataset.storeUrl;

  document.getElementById('btn-new-tenant')?.addEventListener('click', () => {
    document.getElementById('nt-name').value = '';
    document.getElementById('nt-plan').value = '';
    modal.show('#tenant-modal');
  });

  document.getElementById('nt-save')?.addEventListener('click', async () => {
    const name = document.getElementById('nt-name').value.trim();
    const planId = document.getElementById('nt-plan').value || null;
    if (!name) { toast.error('El nombre es obligatorio.'); return; }

    try {
      const payload = {
        name: document.getElementById('nt-name').value.trim(),
        plan_id: document.getElementById('nt-plan').value || null,
        admin_name:  document.getElementById('nt-admin-name').value.trim(),
        admin_email: document.getElementById('nt-admin-email').value.trim(),
        admin_password: document.getElementById('nt-admin-pass').value,
    };
    if (!payload.name || !payload.admin_name || !payload.admin_email || payload.admin_password.length < 8) {
        toast.error('Completa el nombre del tenant y los datos del administrador (contraseña 8+).');
        return;
    }
    await apiPost(storeUrl, payload);
      // await apiPost(storeUrl, { name, plan_id: planId });
      toast.success('Tenant creado. Recargando…');
      modal.hide('#tenant-modal');
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      toast.error(err.data?.message || err.message || 'No se pudo crear el tenant.');
    }
  });
}
