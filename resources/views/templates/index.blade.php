@extends('layouts.app')

@section('title', 'Plantillas · Gestor Documental')

@section('content')
    @php $isAdmin = auth()->user()->isAdmin(); @endphp

    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Plantillas</h1>
            <p>Tipos documentales y la estructura de metadatos que capturan.</p>
        </div>
        @if ($isAdmin)
            <button class="btn btn-primary" id="btn-new-tpl">
                <i class="fa-solid fa-plus me-1"></i> Nueva plantilla
            </button>
        @endif
    </div>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Plantilla</th>
                        <th>Área</th>
                        <th>Metadatos</th>
                        <th>Carpeta INPUT</th>
                        @if ($isAdmin)<th class="text-end pe-3">Acciones</th>@endif
                    </tr>
                </thead>
                <tbody id="tpl-tbody">
                    @forelse ($templates as $tpl)
                        <tr data-tpl-id="{{ $tpl->id }}">
                            <td class="ps-3">
                                <a href="{{ route('templates.show', $tpl) }}" class="fw-medium text-decoration-none" style="color: var(--dm-text);">
                                    <i class="fa-solid fa-table-columns me-2" style="color: var(--dm-primary);"></i>{{ $tpl->name }}
                                </a>
                            </td>
                            <td class="small text-muted">{{ $tpl->area->name }}</td>
                            <td><span class="dm-badge dm-badge--primary">{{ $tpl->fields_count }}</span></td>
                            <td class="mono small text-muted">{{ $tpl->input_folder_path ?: '—' }}</td>
                            @if ($isAdmin)
                                <td class="text-end pe-3">
                                    <button class="dm-icon-btn btn-edit-tpl" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                    <button class="dm-icon-btn btn-delete-tpl" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isAdmin ? 5 : 4 }}" class="text-center text-muted py-4">No hay plantillas todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($isAdmin)
        {{-- Template builder modal --}}
        <div class="modal fade" id="tpl-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="border-radius: var(--dm-radius);">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tpl-modal-title">Nueva plantilla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="tpl-form">
                            <input type="hidden" id="tpl-id">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="t-name">Nombre</label>
                                    <input class="form-control" id="t-name" required maxlength="120" placeholder="Ej. Facturas CxC">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="t-area">Área</label>
                                    <select class="form-select" id="t-area" required>
                                        <option value="">Selecciona…</option>
                                        @foreach ($areas as $a)
                                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="t-input">Carpeta INPUT <span class="text-muted">(salida del módulo de captura)</span></label>
                                    <input class="form-control mono" id="t-input" maxlength="255" placeholder="/ruta/a/input/facturas">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="t-ai" role="switch">
                                        <label class="form-check-label" for="t-ai">
                                            Usar IA para completar campos que el XML no trae <span class="text-muted">(ej. C. Costos)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Metadatos <span class="text-muted">(1 a 5)</span></label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-field">
                                    <i class="fa-solid fa-plus me-1"></i> Agregar
                                </button>
                            </div>
                            <div id="fields-list"></div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" id="tpl-save">Guardar plantilla</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Field row template --}}
        <template id="field-row-tpl">
            <div class="field-row dm-card mb-2" style="box-shadow: none;">
                <div class="dm-card__body p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:.72rem;">Nombre del metadato</label>
                            <input class="form-control form-control-sm f-label" placeholder="Ej. Folio" maxlength="80">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" style="font-size:.72rem;">Tipo</label>
                            <select class="form-select form-select-sm f-type">
                                <option value="text">Texto</option>
                                <option value="number">Número</option>
                                <option value="date">Fecha</option>
                                <option value="currency">Moneda</option>
                                <option value="select">Lista</option>
                            </select>
                        </div>
                        <div class="col-md-3 f-options-wrap d-none">
                            <label class="form-label" style="font-size:.72rem;">Opciones (coma)</label>
                            <input class="form-control form-control-sm f-options" placeholder="A, B, C">
                        </div>
                        <div class="col-md-1 text-center">
                            <label class="form-label d-block" style="font-size:.72rem;">Oblig.</label>
                            <input type="checkbox" class="form-check-input f-required">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="dm-icon-btn btn-del-field" title="Quitar"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        @push('scripts')
        <script>
        window.onDM(function (DM) {
            const { apiGet, apiPost, apiPut, apiDelete, toast, modal, withLoading } = DM;
            const routes = {
                store:  '{{ route('templates.store') }}',
                update: (id) => `{{ url('templates') }}/${id}`,
                destroy:(id) => `{{ url('templates') }}/${id}`,
                fetch:  (id) => `{{ url('templates') }}/${id}/json`,
            };

            const form = document.getElementById('tpl-form');
            const idField = document.getElementById('tpl-id');
            const title = document.getElementById('tpl-modal-title');
            const list = document.getElementById('fields-list');
            const rowTpl = document.getElementById('field-row-tpl');
            const MAX = 5;

            function addField(data) {
                if (list.children.length >= MAX) { toast.warning('Máximo 5 metadatos.'); return; }
                const node = rowTpl.content.cloneNode(true);
                const row = node.querySelector('.field-row');
                if (data) {
                    row.querySelector('.f-label').value = data.label || '';
                    row.querySelector('.f-type').value = data.type || 'text';
                    row.querySelector('.f-required').checked = !!data.is_required;
                    if (data.type === 'select') {
                        row.querySelector('.f-options-wrap').classList.remove('d-none');
                        row.querySelector('.f-options').value = (data.options || []).join(', ');
                    }
                }
                list.appendChild(node);
            }

            list.addEventListener('change', (e) => {
                if (e.target.classList.contains('f-type')) {
                    const wrap = e.target.closest('.field-row').querySelector('.f-options-wrap');
                    wrap.classList.toggle('d-none', e.target.value !== 'select');
                }
            });
            list.addEventListener('click', (e) => {
                if (e.target.closest('.btn-del-field')) {
                    if (list.children.length <= 1) { toast.warning('Debe haber al menos un metadato.'); return; }
                    e.target.closest('.field-row').remove();
                }
            });
            document.getElementById('btn-add-field').addEventListener('click', () => addField());

            function collectFields() {
                return [...list.querySelectorAll('.field-row')].map((row) => {
                    const type = row.querySelector('.f-type').value;
                    const f = {
                        label: row.querySelector('.f-label').value.trim(),
                        type,
                        is_required: row.querySelector('.f-required').checked,
                    };
                    if (type === 'select') {
                        f.options = row.querySelector('.f-options').value
                            .split(',').map(s => s.trim()).filter(Boolean);
                    }
                    return f;
                });
            }

            function openCreate() {
                form.reset(); idField.value = ''; list.innerHTML = '';
                document.getElementById('t-ai').checked = false;
                title.textContent = 'Nueva plantilla';
                addField(); // start with one
                modal.show('#tpl-modal');
            }

            document.getElementById('btn-new-tpl').addEventListener('click', openCreate);

            // Preselect area if arriving from an area page (?area=ID).
            const urlArea = new URLSearchParams(location.search).get('area');

            document.getElementById('tpl-tbody').addEventListener('click', async (e) => {
                const row = e.target.closest('tr[data-tpl-id]');
                if (!row) return;
                const id = row.dataset.tplId;

                if (e.target.closest('.btn-edit-tpl')) {
                    try {
                        const t = await apiGet(routes.fetch(id));
                        form.reset(); idField.value = id; list.innerHTML = '';
                        document.getElementById('t-name').value = t.name;
                        document.getElementById('t-area').value = t.area_id;
                        document.getElementById('t-input').value = t.input_folder_path || '';
                        document.getElementById('t-ai').checked = !!t.ai_enabled;
                        (t.fields || []).forEach(addField);
                        if (!list.children.length) addField();
                        title.textContent = 'Editar plantilla';
                        modal.show('#tpl-modal');
                    } catch (err) { toast.error(err.message); }
                    return;
                }
                if (e.target.closest('.btn-delete-tpl')) {
                    const ok = await DM.confirm({
                        title: 'Eliminar plantilla',
                        message: '¿Eliminar esta plantilla? Se eliminarán sus campos de metadatos.',
                        confirmText: 'Eliminar',
                        danger: true,
                    });
                    if (!ok) return;
                    try { const r = await apiDelete(routes.destroy(id)); toast.success(r.message); row.remove(); }
                    catch (err) { toast.error(err.message); }
                }
            });

            document.getElementById('tpl-save').addEventListener('click', async (e) => {
                const fields = collectFields();
                if (!fields.length || fields.some(f => !f.label)) { toast.error('Cada metadato necesita un nombre.'); return; }

                const payload = {
                    area_id: document.getElementById('t-area').value,
                    name: document.getElementById('t-name').value.trim(),
                    input_folder_path: document.getElementById('t-input').value.trim(),
                    naming_rule: 'same_name',
                    ai_enabled: document.getElementById('t-ai').checked,
                    fields,
                };
                const id = idField.value;
                try {
                    await withLoading(e.currentTarget, async () => {
                        if (id) { await apiPut(routes.update(id), payload); toast.success('Plantilla actualizada. Recargando…'); }
                        else    { await apiPost(routes.store, payload); toast.success('Plantilla creada. Recargando…'); }
                        modal.hide('#tpl-modal');
                        setTimeout(() => location.reload(), 700);
                    });
                } catch (err) {
                    const msg = err.data?.errors ? Object.values(err.data.errors)[0][0] : err.message;
                    toast.error(msg);
                }
            });

            // Auto-open builder if navigated with ?area=ID
            if (urlArea) { openCreate(); document.getElementById('t-area').value = urlArea; }
        });
        </script>
        @endpush
    @endif
@endsection
