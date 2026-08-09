@extends('layouts.app')

@section('title', 'Usuarios · Gestor Documental')

@section('content')
    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Usuarios</h1>
            <p>Administra las cuentas y sus roles.</p>
        </div>
        <button class="btn btn-primary" id="btn-new-user">
            <i class="fa-solid fa-plus me-1"></i> Nuevo usuario
        </button>
    </div>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    @forelse ($users as $u)
                        <tr data-user-id="{{ $u->id }}"
                            data-name="{{ $u->name }}" data-email="{{ $u->email }}" data-role="{{ $u->role }}">
                            <td class="ps-3 fw-medium">{{ $u->name }}</td>
                            <td class="mono small text-muted">{{ $u->email }}</td>
                            <td>
                                <span class="dm-badge {{ $u->role === 'admin' ? 'dm-badge--primary' : '' }}">
                                    {{ $u->role === 'admin' ? 'Administrador' : 'Miembro' }}
                                </span>
                            </td>
                            <td>
                                <span class="dm-badge {{ $u->is_active ? 'dm-badge--accent' : '' }}" data-status>
                                    {{ $u->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('users.permissions', $u) }}"
                                    class="btn btn-sm btn-outline-secondary" title="Permisos por área">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </a>
                                <button class="dm-icon-btn btn-edit" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                <button class="dm-icon-btn btn-toggle" title="Activar/Desactivar"><i class="fa-solid fa-power-off"></i></button>
                                <button class="dm-icon-btn btn-delete" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay usuarios todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>

    {{-- Create/Edit modal --}}
    <div class="modal fade" id="user-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--dm-radius);">
                <div class="modal-header">
                    <h5 class="modal-title" id="user-modal-title">Nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="user-form">
                        <input type="hidden" id="user-id">
                        <div class="mb-3">
                            <label class="form-label" for="f-name">Nombre</label>
                            <input class="form-control" id="f-name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="f-email">Correo</label>
                            <input type="email" class="form-control" id="f-email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="f-password">
                                Contraseña <span class="text-muted" id="pw-hint">(mín. 8 caracteres)</span>
                            </label>
                            <input type="password" class="form-control" id="f-password" name="password" minlength="8">
                        </div>
                        <div class="mb-1">
                            <label class="form-label" for="f-role">Rol</label>
                            <select class="form-select" id="f-role" name="role">
                                <option value="member">Miembro</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" id="user-save">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
        window.onDM(function (DM) {
            const { apiPost, apiPut, apiDelete, toast, modal, serializeForm, withLoading } = DM;
            const routes = {
                store:  '{{ route('users.store') }}',
                update: (id) => `{{ url('users') }}/${id}`,
                toggle: (id) => `{{ url('users') }}/${id}/toggle`,
                destroy:(id) => `{{ url('users') }}/${id}`,
            };

            const form = document.getElementById('user-form');
            const idField = document.getElementById('user-id');
            const title = document.getElementById('user-modal-title');
            const pwField = document.getElementById('f-password');
            const pwHint = document.getElementById('pw-hint');

            function openCreate() {
                form.reset(); idField.value = '';
                title.textContent = 'Nuevo usuario';
                pwField.required = true;
                pwHint.textContent = '(mín. 8 caracteres)';
                modal.show('#user-modal');
            }

            function openEdit(row) {
                form.reset();
                idField.value = row.dataset.userId;
                document.getElementById('f-name').value = row.dataset.name;
                document.getElementById('f-email').value = row.dataset.email;
                document.getElementById('f-role').value = row.dataset.role;
                title.textContent = 'Editar usuario';
                pwField.required = false;
                pwHint.textContent = '(dejar en blanco para no cambiar)';
                modal.show('#user-modal');
            }

            document.getElementById('btn-new-user').addEventListener('click', openCreate);

            document.getElementById('users-tbody').addEventListener('click', async (e) => {
                const row = e.target.closest('tr[data-user-id]');
                if (!row) return;
                const id = row.dataset.userId;

                if (e.target.closest('.btn-edit')) return openEdit(row);

                if (e.target.closest('.btn-toggle')) {
                    try {
                        const r = await apiPost(routes.toggle(id));
                        toast.success(r.message);
                        const badge = row.querySelector('[data-status]');
                        badge.textContent = r.is_active ? 'Activo' : 'Inactivo';
                        badge.classList.toggle('dm-badge--accent', r.is_active);
                    } catch (err) { toast.error(err.message); }
                    return;
                }

                if (e.target.closest('.btn-delete')) {
                    const ok = await DM.confirm({
                        title: 'Eliminar usuario',
                        message: '¿Eliminar este usuario? Esta acción no se puede deshacer.',
                        confirmText: 'Eliminar',
                        danger: true,
                    });
                    if (!ok) return;
                    try {
                        const r = await apiDelete(routes.destroy(id));
                        toast.success(r.message);
                        row.remove();
                    } catch (err) { toast.error(err.message); }
                }
            });

            document.getElementById('user-save').addEventListener('click', async (e) => {
                const payload = serializeForm(form);
                const id = idField.value;
                if (!id && !payload.password) { toast.error('La contraseña es obligatoria.'); return; }
                if (id && !payload.password) delete payload.password;

                try {
                    await withLoading(e.currentTarget, async () => {
                        if (id) {
                            await apiPut(routes.update(id), payload);
                            toast.success('Usuario actualizado. Recargando…');
                        } else {
                            await apiPost(routes.store, payload);
                            toast.success('Usuario creado. Recargando…');
                        }
                        modal.hide('#user-modal');
                        setTimeout(() => location.reload(), 700);
                    });
                } catch (err) {
                    const msg = err.data && err.data.errors
                        ? Object.values(err.data.errors)[0][0]
                        : err.message;
                    toast.error(msg);
                }
            });
        });
        </script>
    @endpush
@endsection
