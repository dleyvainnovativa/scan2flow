@extends('layouts.app')

@section('title', 'Documentos')

@php
// Templates for the picker: only those in areas the user can view. Grouped
// by area so the <select> reads naturally.
    $templatesByArea = \App\Models\Template::with('area')
    ->whereIn('area_id', $areas->pluck('id'))
    ->orderBy('name')
    ->get()
    ->groupBy(fn ($t) => optional($t->area)->name ?? '—');

    $hasActiveFilters = $areaId || $templateId || $status || $from || $to
    || collect($metaFilters)->filter()->isNotEmpty();

    $statusLabels = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
    $statusStyles = [
    'pending' => 'color: var(--dm-warning);',
    'approved' => 'color: var(--dm-success);',
    'rejected' => 'color: var(--dm-danger);',
    ];
    @endphp

    @section('content')
    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Búsqueda de Documentos</h1>
            <p>Todos los documentos a los que tienes acceso.</p>
        </div>
    </div>

    {{-- ── Filter / search bar ──────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('documents.all') }}" class="dm-card mb-3">
        <div class="dm-card__body py-3">
            {{-- Row 1: keyword + core filters --}}
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="f-q" style="font-size:.72rem;">Búsqueda (metadatos + OCR)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input class="form-control form-control-sm" id="f-q" name="q"
                            value="{{ $q }}" placeholder="Escribe para buscar…" autocomplete="off">
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="f-area" style="font-size:.72rem;">Área</label>
                    <select class="form-select form-select-sm" id="f-area" name="area_id">
                        <option value="">Todas</option>
                        @foreach ($areas as $a)
                        <option value="{{ $a->id }}" @selected($areaId===$a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="f-template" style="font-size:.72rem;">Plantilla</label>
                    <select class="form-select form-select-sm" id="f-template" name="template_id"
                        onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach ($templatesByArea as $areaName => $tpls)
                        <optgroup label="{{ $areaName }}">
                            @foreach ($tpls as $tpl)
                            <option value="{{ $tpl->id }}" @selected($templateId===$tpl->id)>{{ $tpl->name }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="f-status" style="font-size:.72rem;">Estado</label>
                    <select class="form-select form-select-sm" id="f-status" name="status">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $val => $label)
                        <option value="{{ $val }}" @selected($status===$val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="f-from" style="font-size:.72rem;">Desde</label>
                    <input class="form-control form-control-sm" type="date" id="f-from" name="from" value="{{ $from }}">
                </div>
            </div>

            {{-- Row 2: date-to + dynamic metadata (only when a template is picked) --}}
            <div class="row g-2 align-items-end mt-1">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="f-to" style="font-size:.72rem;">Hasta</label>
                    <input class="form-control form-control-sm" type="date" id="f-to" name="to" value="{{ $to }}">
                </div>

                @if ($activeTemplate)
                @foreach ($activeTemplate->fields as $field)
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="fm-{{ $field->key }}" style="font-size:.72rem;">{{ $field->label }}</label>
                    @if ($field->type === 'select')
                    <select class="form-select form-select-sm" id="fm-{{ $field->key }}" name="f[{{ $field->key }}]">
                        <option value="">Todos</option>
                        @foreach (($field->options ?? []) as $opt)
                        <option value="{{ $opt }}" @selected(($metaFilters[$field->key] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                    @else
                    <input class="form-control form-control-sm {{ in_array($field->type, ['number','currency']) ? 'mono' : '' }}"
                        id="fm-{{ $field->key }}" name="f[{{ $field->key }}]"
                        type="{{ $field->type === 'date' ? 'date' : 'text' }}"
                        value="{{ $metaFilters[$field->key] ?? '' }}"
                        placeholder="{{ $field->type === 'text' ? 'contiene…' : '' }}">
                    @endif
                </div>
                @endforeach
                @endif

                <div class="col-12 col-lg d-flex gap-1 justify-content-lg-end">
                    <button class="btn btn-sm btn-primary"><i class="fa-solid fa-filter me-1"></i> Aplicar</button>
                    @if ($hasActiveFilters || $q !== '')
                    <a href="{{ route('documents.all') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar">
                        <i class="fa-solid fa-xmark me-1"></i> Limpiar
                    </a>
                    @endif
                </div>
            </div>

            @unless ($activeTemplate)
            <p class="text-muted mt-2 mb-0" style="font-size:.72rem;">
                <i class="fa-solid fa-circle-info me-1"></i>
                Selecciona una plantilla para filtrar por sus campos de metadatos.
            </p>
            @endunless
        </div>
    </form>

    {{-- ── SEARCH MODE ──────────────────────────────────────────────────── --}}
    @if ($mode === 'search')
    <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="text-muted small mb-0">
            {{ $results->count() }} resultado(s) para «{{ $q }}»
            <span class="ms-1" style="font-size:.72rem;">· ordenados por relevancia</span>
        </p>
    </div>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Documento</th>
                        <th>Área</th>
                        <th>Plantilla</th>
                        <th>Coincidencia</th>
                        <th>Cargado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $r)
                    @php $doc = $r->document; @endphp
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('documents.show', $doc) }}" class="fw-medium text-decoration-none d-block" style="color: var(--dm-text);">
                                <i class="fa-regular fa-file-pdf me-2" style="color: var(--dm-danger);"></i>{{ $doc->title }}
                            </a>
                            @if ($r->snippet)
                            <span class="small text-muted d-block mt-1" style="max-width: 520px;">{!! $r->snippet !!}</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ optional($doc->area)->name ?? '—' }}</td>
                        <td class="small text-muted">{{ optional($doc->template)->name ?? '—' }}</td>
                        <td>
                            <span class="badge" style="background: var(--dm-surface-2); color: var(--dm-text-muted); font-weight:500;">
                                {{ ['metadata' => 'Metadatos', 'ocr' => 'OCR', 'both' => 'Ambos'][$r->matchedIn] ?? $r->matchedIn }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $doc->created_at->format('d/m/Y') }}</td>
                        <td class="text-end pe-3">
                            <a href="{{ route('documents.show', $doc) }}" class="dm-icon-btn" title="Ver"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No se encontraron documentos para «{{ $q }}».
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($results->isNotEmpty())
    <p class="text-muted mt-2" style="font-size:.72rem;">
        <i class="fa-solid fa-circle-info me-1"></i>
        La búsqueda muestra los resultados más relevantes. Afina los filtros o el término para acotar.
    </p>
    @endif

    {{-- ── BROWSE MODE ──────────────────────────────────────────────────── --}}
    @else
    <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="text-muted small mb-0">{{ $documents->total() }} documento(s)</p>
    </div>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Título del PDF</th>
                        <th>Área</th>
                        <th>Plantilla</th>
                        <th>Estado</th>
                        <th>Cargado</th>
                        <th>Páginas</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $doc)
                    <tr>
                        <td class="ps-3">
                            <a href="{{ route('documents.show', $doc) }}" class="fw-medium text-decoration-none" style="color: var(--dm-text);">
                                <i class="fa-regular fa-file-pdf me-2" style="color: var(--dm-danger);"></i>{{ $doc->title }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ optional($doc->area)->name ?? '—' }}</td>
                        <td class="small text-muted">{{ optional($doc->template)->name ?? '—' }}</td>
                        <td class="small">
                            <span style="{{ $statusStyles[$doc->status] ?? '' }}">
                                <i class="fa-solid fa-circle me-1" style="font-size:.5rem; vertical-align: middle;"></i>{{ $statusLabels[$doc->status] ?? $doc->status }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $doc->created_at->format('d/m/Y') }}</td>
                        <td class="small text-muted">{{ $doc->page_count ?? '—' }} p.</td>
                        <td class="text-end pe-3">
                            <a href="{{ route('documents.show', $doc) }}" class="dm-icon-btn" title="Ver"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay documentos que coincidan con los filtros.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($documents->hasPages())
    <div class="mt-3">{{ $documents->links() }}</div>
    @endif
    @endif
    @endsection