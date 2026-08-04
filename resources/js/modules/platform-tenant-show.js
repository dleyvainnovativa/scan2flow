/**
 * modules/platform-tenant-show.js — tenant detail (top-up, assign plan, status).
 * Loaded when <body data-page="platform.tenant-show">.
 */

import { apiPost } from '../helpers/http.js';
import { toast } from '../helpers/toast.js';
import { dialog } from '../helpers/dialog.js';

export function init() {
  const data = document.getElementById('platform-tenant-data');
  if (!data) return;
  const { planUrl, topupUrl, statusUrl } = data.dataset;

  // ---- Top up pages ----
  document.getElementById('btn-topup')?.addEventListener('click', async () => {
    const input = document.getElementById('topup-pages');
    const pages = parseInt(input.value, 10);
    if (!pages || pages < 1) { toast.error('Ingresa un número de páginas válido.'); return; }

    try {
      const r = await apiPost(topupUrl, { pages });
      toast.success(r.message);
      document.getElementById('balance-value').textContent = Number(r.new_balance).toLocaleString();
      input.value = '';
      setTimeout(() => location.reload(), 900);
    } catch (err) {
      toast.error(err.data?.message || err.message || 'No se pudo recargar.');
    }
  });

  // ---- Assign plan ----
  document.getElementById('btn-assign-plan')?.addEventListener('click', async () => {
    const planId = document.getElementById('plan-select').value;
    const grantBundle = document.getElementById('grant-bundle').checked;

    try {
      const r = await apiPost(planUrl, { plan_id: planId, grant_page_bundle: grantBundle });
      toast.success(r.message);
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      toast.error(err.data?.message || err.message || 'No se pudo asignar el plan.');
    }
  });

  // ---- Suspend / activate ----
  async function setStatus(status, confirmMsg) {
    const ok = await dialog.confirm({
      title: status === 'suspended' ? 'Suspender tenant' : 'Activar tenant',
      message: confirmMsg,
      confirmText: status === 'suspended' ? 'Suspender' : 'Activar',
      danger: status === 'suspended',
    });
    if (!ok) return;
    try {
      const r = await apiPost(statusUrl, { status });
      toast.success(r.message);
      setTimeout(() => location.reload(), 700);
    } catch (err) {
      toast.error(err.data?.message || err.message || 'No se pudo cambiar el estado.');
    }
  }


// ---- Reassign user ----
document.getElementById('btn-reassign-user')?.addEventListener('click', async () => {
  const userId = parseInt(document.getElementById('ru-user').value, 10);
  const toTenant = parseInt(document.getElementById('ru-to').value, 10);
  if (!userId || !toTenant) { toast.error('Ingresa ID de usuario y tenant destino.'); return; }

  const ok = await dialog.confirm({
    title: 'Reasignar usuario',
    message: `¿Mover el usuario #${userId} al tenant #${toTenant}?`,
    confirmText: 'Reasignar',
  });
  if (!ok) return;

  try {
    const r = await apiPost(data.dataset.reassignUserUrl, { user_id: userId, to_tenant: toTenant });
    toast.success(r.message);
  } catch (err) {
    toast.error(err.data?.message || err.message || 'No se pudo reasignar.');
  }
});

// ---- Area migration: preview (dry-run) ----
let areaMoveArmed = null; // holds {areaId, toTenant} once previewed
document.getElementById('btn-preview-area')?.addEventListener('click', async () => {
  const areaId = parseInt(document.getElementById('ma-area').value, 10);
  const toTenant = parseInt(document.getElementById('ma-to').value, 10);
  const out = document.getElementById('area-preview');
  const migrateBtn = document.getElementById('btn-migrate-area');
  if (!areaId || !toTenant) { toast.error('Ingresa ID de área y tenant destino.'); return; }

  try {
    const r = await apiPost(data.dataset.areaPreviewUrl, { area_id: areaId, to_tenant: toTenant });
    const c = r.preview.counts;
    out.innerHTML = `Se moverán: <strong>${c.templates}</strong> plantillas, ` +
      `<strong>${c.documents}</strong> documentos, ${c.document_metadata} metadatos, ` +
      `${c.document_contents} contenidos, ${c.permissions} permisos, y ${r.preview.files} archivos.`;
    areaMoveArmed = { areaId, toTenant };
    migrateBtn.disabled = false; // arm the commit button
  } catch (err) {
    out.textContent = '';
    migrateBtn.disabled = true;
    areaMoveArmed = null;
    toast.error(err.data?.message || err.message || 'No se pudo previsualizar.');
  }
});

// ---- Area migration: commit ----
document.getElementById('btn-migrate-area')?.addEventListener('click', async () => {
  if (!areaMoveArmed) { toast.error('Previsualiza primero.'); return; }

  const ok = await dialog.confirm({
    title: 'Migrar área',
    message: 'Esto moverá el área y TODO su contenido (plantillas, documentos, archivos) al tenant destino. La operación no se revierte automáticamente. ¿Continuar?',
    confirmText: 'Migrar',
    danger: true,
  });
  if (!ok) return;

  try {
    const r = await apiPost(data.dataset.areaMoveUrl, {
      area_id: areaMoveArmed.areaId,
      to_tenant: areaMoveArmed.toTenant,
      confirm: 1,
    });
    toast.success(r.message);
    document.getElementById('btn-migrate-area').disabled = true;
    areaMoveArmed = null;
  } catch (err) {
    toast.error(err.data?.message || err.message || 'No se pudo migrar.');
  }
});

  document.getElementById('btn-suspend')?.addEventListener('click', () =>
    setStatus('suspended', 'Los usuarios de este tenant no podrán acceder mientras esté suspendido. ¿Continuar?'));
  document.getElementById('btn-activate')?.addEventListener('click', () =>
    setStatus('active', '¿Reactivar el acceso para este tenant?'));
}
