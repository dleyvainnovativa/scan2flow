@extends('layouts.app')

@section('title', 'Documentos · ' . $area->name)

@section('content')
    @php $canEdit = auth()->user()->canOnArea($area, 'edit'); @endphp

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('areas.index') }}">Áreas</a></li>
            <li class="breadcrumb-item"><a href="{{ route('areas.show', $area) }}">{{ $area->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Documentos</li>
        </ol>
    </nav>

    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Documentos</h1>
            <p>{{ $area->name }}</p>
        </div>
        @if ($canEdit && $activeTemplate)
            <button class="btn btn-primary" id="btn-upload">
                <i class="fa-solid fa-upload me-1"></i> Cargar documento
            </button>
        @endif
    </div>

    @if ($templates->isEmpty())
        <div class="dm-card"><div class="dm-card__body text-center text-muted py-5">
            Esta área no tiene plantillas. Crea una plantilla primero.
        </div></div>
    @else
        <ul class="nav nav-pills mb-3 gap-1">
            @foreach ($templates as $tpl)
                <li class="nav-item">
                    <a class="nav-link {{ $activeTemplate && $tpl->id === $activeTemplate->id ? 'active' : '' }}"
                       href="{{ route('documents.index', ['area' => $area->id, 'template' => $tpl->id]) }}"
                       style="{{ $activeTemplate && $tpl->id === $activeTemplate->id ? 'background: var(--dm-primary);' : 'color: var(--dm-text-muted);' }}">
                        <i class="fa-solid fa-table-columns me-1"></i>{{ $tpl->name }}
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Dynamic metadata filter bar --}}
        @if ($activeTemplate)
            <form method="GET" action="{{ route('documents.index', $area) }}" class="dm-card mb-3">
                <input type="hidden" name="template" value="{{ $activeTemplate->id }}">
                <div class="dm-card__body py-3">
                    <div class="row g-2 align-items-end">
                        @foreach ($activeTemplate->fields as $field)
                            <div class="col-6 col-md-3 col-xl-2">
                                <label class="form-label" for="f-{{ $field->key }}" style="font-size:.72rem;">{{ $field->label }}</label>
                                @if ($field->type === 'select')
                                    <select class="form-select form-select-sm" id="f-{{ $field->key }}" name="f[{{ $field->key }}]">
                                        <option value="">Todos</option>
                                        @foreach (($field->options ?? []) as $opt)
                                            <option value="{{ $opt }}" @selected(($filters[$field->key] ?? '') === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="form-control form-control-sm {{ in_array($field->type, ['number','currency']) ? 'mono' : '' }}"
                                           id="f-{{ $field->key }}" name="f[{{ $field->key }}]"
                                           type="{{ $field->type === 'date' ? 'date' : 'text' }}"
                                           value="{{ $filters[$field->key] ?? '' }}"
                                           placeholder="{{ $field->type === 'text' ? 'contiene…' : '' }}">
                                @endif
                            </div>
                        @endforeach
                        <div class="col-6 col-md-3 col-xl-2 d-flex gap-1">
                            <button class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
                            @if (collect($filters)->filter()->isNotEmpty())
                                <a href="{{ route('documents.index', ['area' => $area->id, 'template' => $activeTemplate->id]) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-xmark"></i></a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        @endif

        <div class="dm-card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th class="ps-3">Título del PDF</th>
                            @foreach ($activeTemplate->fields as $field)
                                <th>{{ $field->label }}</th>
                            @endforeach
                            <th>Cargado</th>
                            <th>Páginas</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rows = $documents->map(function ($doc) {
                                $byKey = $doc->metadata->mapWithKeys(fn ($m) => [$m->field->key => $m->value]);
                                return [$doc, $byKey];
                            });
                        @endphp
                        @forelse ($rows as [$doc, $byKey])
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('documents.show', $doc) }}" class="fw-medium text-decoration-none" style="color: var(--dm-text);">
                                        <i class="fa-regular fa-file-pdf me-2" style="color: var(--dm-danger);"></i>{{ $doc->title }}
                                    </a>
                                </td>
                                @foreach ($activeTemplate->fields as $field)
                                    <td class="{{ in_array($field->type, ['number','currency']) ? 'mono' : '' }} small">
                                        {{ $byKey[$field->key] ?? '—' }}
                                    </td>
                                @endforeach
                                <td class="small text-muted">{{ $doc->created_at->format('d/m/Y') }}</td>
                                <td class="small text-muted">{{ $doc->page_count ?? '—' }} p.</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('documents.show', $doc) }}" class="dm-icon-btn" title="Ver"><i class="fa-solid fa-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $activeTemplate->fields->count() + 3 }}" class="text-center text-muted py-4">
                                    No hay documentos que coincidan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($documents instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-3">{{ $documents->links() }}</div>
        @endif
    @endif

    {{-- Upload modal --}}
    @if ($canEdit && $activeTemplate)
        <div class="modal fade" id="upload-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: var(--dm-radius);">
                    <div class="modal-header">
                        <h5 class="modal-title">Cargar documento — {{ $activeTemplate->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="upload-form">
                            <div class="mb-3">
                                <label class="form-label" for="d-title">Título</label>
                                <input class="form-control" id="d-title" name="title" required maxlength="255">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="d-pdf">Archivo PDF</label>
                                    <input type="file" class="form-control" id="d-pdf" name="pdf" accept="application/pdf" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="d-xml">XML <span class="text-muted">(opcional)</span></label>
                                    <input type="file" class="form-control" id="d-xml" name="xml" accept=".xml,text/xml,application/xml">
                                </div>
                            </div>
                            <hr>
                            <p class="form-label mb-2">Metadatos</p>
                            @foreach ($activeTemplate->fields as $field)
                                <div class="mb-2">
                                    <label class="form-label" for="m-{{ $field->key }}">
                                        {{ $field->label }}
                                        @if ($field->is_required)<span style="color: var(--dm-danger);">*</span>@endif
                                    </label>
                                    @if ($field->type === 'select')
                                        <select class="form-select" id="m-{{ $field->key }}" name="metadata[{{ $field->key }}]">
                                            <option value="">—</option>
                                            @foreach (($field->options ?? []) as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="form-control {{ in_array($field->type, ['number','currency']) ? 'mono' : '' }}"
                                               id="m-{{ $field->key }}" name="metadata[{{ $field->key }}]"
                                               type="{{ $field->type === 'date' ? 'date' : ($field->type === 'number' ? 'number' : 'text') }}"
                                               @if($field->type === 'currency') inputmode="decimal" placeholder="0.00" @endif>
                                    @endif
                                </div>
                            @endforeach
                            <hr>
                            <div class="mb-1">
                                <label class="form-label" for="d-content">Texto del documento <span class="text-muted">(opcional, para probar búsqueda OCR)</span></label>
                                <textarea class="form-control" id="d-content" name="content_text" rows="3"
                                          placeholder="Pega aquí texto para indexar mientras el módulo de captura no está disponible."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" id="upload-save">Cargar</button>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
        <script>
        window.onDM(function (DM) {
            const { toast, modal, withLoading } = DM;
            const uploadUrl = '{{ route('documents.store', $activeTemplate) }}';
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const form = document.getElementById('upload-form');

            document.getElementById('btn-upload').addEventListener('click', () => {
                form.reset(); modal.show('#upload-modal');
            });

            document.getElementById('upload-save').addEventListener('click', async (e) => {
                if (!form.checkValidity()) { form.reportValidity(); return; }
                const fd = new FormData(form);
                try {
                    await withLoading(e.currentTarget, async () => {
                        const res = await fetch(uploadUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: fd,
                        });
                        const body = await res.json();
                        if (!res.ok) {
                            const msg = body.errors ? (Object.values(body.errors)[0][0] || Object.values(body.errors)[0]) : body.message;
                            toast.error(msg || 'No se pudo cargar.'); return;
                        }
                        toast.success('Documento cargado. Abriendo…');
                        setTimeout(() => location.href = body.redirect, 700);
                    });
                } catch (err) { toast.error('Error de red al cargar.'); }
            });
        });
        </script>
        @endpush
    @endif
@endsection
