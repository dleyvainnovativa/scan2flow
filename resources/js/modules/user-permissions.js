/**
 * modules/user-permissions.js — user-centric permission matrix.
 * Loaded when <body data-page="users.permissions">.
 *
 * Individual toggles POST to the existing areas/{area}/permissions endpoint;
 * bulk actions POST to users/{user}/permissions/bulk. Both return the resulting
 * flags, and we sync the checkboxes to match (imply-view means checking "edit"
 * also turns on "view").
 */

import { apiPost } from '../helpers/http.js';
import { toast } from '../helpers/toast.js';
import { dialog } from '../helpers/dialog.js';

const FLAGS = ['can_view', 'can_download', 'can_edit', 'can_approve'];

export function init() {
  const data = document.getElementById('user-perms-data');
  if (!data) return;
  const userId = data.dataset.userId;
  const bulkUrl = data.dataset.bulkUrl;

  // Read a row's current flags from its checkboxes.
  function readRow(row) {
    const flags = {};
    for (const f of FLAGS) {
      const box = row.querySelector(`.perm-box[data-flag="${f}"]`);
      flags[f] = box ? box.checked : false;
    }
    return flags;
  }

  // Write flags back onto a row's checkboxes (sync to server truth).
  function writeRow(row, flags) {
    for (const f of FLAGS) {
      const box = row.querySelector(`.perm-box[data-flag="${f}"]`);
      if (box) box.checked = flags ? !!flags[f] : false;
    }
  }

  // ---- Individual toggle → existing areas.permissions endpoint ----
  document.querySelectorAll('#perm-matrix tr[data-area-id]').forEach((row) => {
    const permUrl = row.dataset.permUrl;

    row.querySelectorAll('.perm-box').forEach((box) => {
      box.addEventListener('change', async () => {
        const flags = readRow(row);
        // Optimistically reflect imply-view so it doesn't flicker.
        if (flags.can_download || flags.can_edit || flags.can_approve) {
          flags.can_view = true;
        }
        writeRow(row, flags);

        try {
          const r = await apiPost(permUrl, { user_id: userId, ...flags });
          // Sync to what the server actually saved (null = detached = all off).
          writeRow(row, r.flags);
        } catch (err) {
          toast.error(err.data?.message || err.message || 'No se pudo guardar.');
          // Revert on failure by re-reading isn't possible (we changed it), so
          // flip the just-changed box back.
          box.checked = !box.checked;
        }
      });
    });
  });

  // ---- Bulk actions ----
  document.querySelectorAll('[data-bulk]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const kind = btn.dataset.bulk;         // 'can_view' | 'can_download' | 'revoke-all'
      const value = btn.dataset.value === '1';
      const rows = [...document.querySelectorAll('#perm-matrix tr[data-area-id]')];
      const areaIds = rows.map((r) => parseInt(r.dataset.areaId, 10));
      if (areaIds.length === 0) return;

      // 'revoke-all' = set can_view false everywhere (which, with nothing else,
      // detaches). We model it as flag can_view=false; the server detaches when
      // all flags end up false. To fully revoke, send each flag off — simplest:
      // use can_view=false AND rely on server detach only if other flags are
      // already off. For a true "revoke everything", confirm + send can_view off
      // after clearing others. Here we do a hard revoke via a confirm.
      if (kind === 'revoke-all') {
        const ok = await dialog.confirm({
          title: 'Revocar todo',
          message: `¿Quitar TODOS los permisos de este usuario en todas las áreas?`,
          confirmText: 'Revocar todo',
          danger: true,
        });
        if (!ok) return;

        // Send each flag off in sequence so the row ends fully detached.
        try {
          for (const flag of FLAGS) {
            await apiPost(bulkUrl, { flag, value: false, area_ids: areaIds });
          }
          rows.forEach((r) => writeRow(r, null));
          toast.success('Permisos revocados en todas las áreas.');
        } catch (err) {
          toast.error(err.data?.message || err.message || 'No se pudo revocar.');
        }
        return;
      }

      // Grant/ungrant a single flag across all areas.
      try {
        const r = await apiPost(bulkUrl, { flag: kind, value, area_ids: areaIds });
        // Sync each row from the returned per-area flags.
        rows.forEach((row) => {
          const res = r.results?.[row.dataset.areaId];
          writeRow(row, res ?? null);
        });
        toast.success('Permisos actualizados.');
      } catch (err) {
        toast.error(err.data?.message || err.message || 'No se pudo actualizar.');
      }
    });
  });
}
