@extends('layouts.app')

@section('title', 'Bóvedas · Gestor Documental')

@section('content')
    @php $isAdmin = auth()->user()->isAdmin(); @endphp

    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Bóvedas</h1>
            <p>Espacios de trabajo que agrupan plantillas y documentos.</p>
        </div>
        @if ($isAdmin)
            <button class="btn btn-primary" id="btn-new-area">
                <i class="fa-solid fa-plus me-1"></i> Nueva área
            </button>
        @endif
    </div>

    <div class="row g-3" id="areas-grid">
        @forelse ($areas as $area)
            <div class="col-12 col-sm-6 col-xl-4" data-area-id="{{ $area->id }}"
                 data-name="{{ $area->name }}" data-description="{{ $area->description }}">
                <div class="dm-card dm-card--hover h-100">
                    <div class="dm-card__body">
                        <div class="d-flex justify-content-between align-items-start">
                            <a href="{{ route('areas.show', $area) }}" class="text-decoration-none">
                                <h2 style="font-size: 1.05rem; font-weight: 600; color: var(--dm-text);">
                                    <i class="fa-solid fa-sitemap me-2" style="color: var(--dm-primary);"></i>{{ $area->name }}
                                </h2>
                            </a>
                            @if ($isAdmin)
                                <div class="dropdown">
                                    <button class="dm-icon-btn" data-bs-toggle="dropdown" aria-label="Opciones">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item btn-edit-area"><i class="fa-solid fa-pen me-2"></i>Editar</button></li>
                                        <li><button class="dropdown-item text-danger btn-delete-area"><i class="fa-solid fa-trash me-2"></i>Eliminar</button></li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                        <p class="text-muted small mt-1 mb-3">{{ $area->description ?: 'Sin descripción' }}</p>
                        <div class="d-flex gap-2">
                            <span class="dm-badge"><i class="fa-solid fa-table-columns"></i> {{ $area->templates_count }} plantillas</span>
                            <span class="dm-badge"><i class="fa-solid fa-file-lines"></i> {{ $area->documents_count }} docs</span>
                            <span class="dm-badge"><i class="fa-solid fa-copy"></i> {{ (int) $area->pages_sum }} págs</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="dm-card"><div class="dm-card__body text-center text-muted py-5">
                    <i class="fa-solid fa-sitemap mb-2" style="font-size: 1.5rem;"></i>
                    <p class="mb-0">No hay áreas todavía.@if($isAdmin) Crea la primera con el botón de arriba.@endif</p>
                </div></div>
            </div>
        @endforelse
    </div>

    @if ($isAdmin)
        {{-- Create/Edit area modal --}}
        <div class="modal fade" id="area-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: var(--dm-radius);">
                    <div class="modal-header">
                        <h5 class="modal-title" id="area-modal-title">Nueva área</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="area-form">
                            <input type="hidden" id="area-id">
                            <div class="mb-3">
                                <label class="form-label" for="a-name">Nombre</label>
                                <input class="form-control" id="a-name" name="name" required maxlength="120">
                            </div>
                            <div class="mb-1">
                                <label class="form-label" for="a-desc">Descripción</label>
                                <textarea class="form-control" id="a-desc" name="description" rows="2" maxlength="500"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" id="area-save">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
        window.onDM(function (DM) {
            const { apiPost, apiPut, apiDelete, toast, modal, serializeForm, withLoading } = DM;
            const routes = {
                store:  '{{ route('areas.store') }}',
                update: (id) => `{{ url('areas') }}/${id}`,
                destroy:(id) => `{{ url('areas') }}/${id}`,
            };
            const form = document.getElementById('area-form');
            const idField = document.getElementById('area-id');
            const title = document.getElementById('area-modal-title');

            document.getElementById('btn-new-area').addEventListener('click', () => {
                form.reset(); idField.value = '';
                title.textContent = 'Nueva área';
                modal.show('#area-modal');
            });

            document.getElementById('areas-grid').addEventListener('click', async (e) => {
                const col = e.target.closest('[data-area-id]');
                if (!col) return;
                const id = col.dataset.areaId;

                if (e.target.closest('.btn-edit-area')) {
                    form.reset();
                    idField.value = id;
                    document.getElementById('a-name').value = col.dataset.name;
                    document.getElementById('a-desc').value = col.dataset.description;
                    title.textContent = 'Editar área';
                    modal.show('#area-modal');
                    return;
                }
                if (e.target.closest('.btn-delete-area')) {
                    const ok = await DM.confirm({
                        title: 'Eliminar área',
                        message: '¿Eliminar esta área? Se eliminarán sus plantillas y documentos.',
                        confirmText: 'Eliminar',
                        danger: true,
                    });
                    if (!ok) return;
                    try { const r = await apiDelete(routes.destroy(id)); toast.success(r.message); col.remove(); }
                    catch (err) { toast.error(err.message); }
                }
            });

            document.getElementById('area-save').addEventListener('click', async (e) => {
                const payload = serializeForm(form);
                const id = idField.value;
                try {
                    await withLoading(e.currentTarget, async () => {
                        if (id) { await apiPut(routes.update(id), payload); toast.success('Área actualizada. Recargando…'); }
                        else    { await apiPost(routes.store, payload); toast.success('Área creada. Recargando…'); }
                        modal.hide('#area-modal');
                        setTimeout(() => location.reload(), 600);
                    });
                } catch (err) {
                    const msg = err.data?.errors ? Object.values(err.data.errors)[0][0] : err.message;
                    toast.error(msg);
                }
            });
        });
        </script>
        @endpush
    @endif
@endsection
