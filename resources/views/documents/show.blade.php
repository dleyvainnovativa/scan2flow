@extends('layouts.app')

@section('title', $document->title . ' · Documentos')

@section('page', 'documents.show')

@section('content')
    @php
        $canEdit = auth()->user()->canOnArea($document->area, 'edit');
        $canDownload = auth()->user()->canOnArea($document->area, 'download');
        $highlight = trim((string) request('q', ''));
    @endphp

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('areas.index') }}">Áreas</a></li>
            <li class="breadcrumb-item"><a href="{{ route('areas.show', $document->area) }}">{{ $document->area->name }}</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('documents.index', ['area' => $document->area_id, 'template' => $document->template_id]) }}">
                    {{ $document->template->name }}
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $document->title }}</li>
        </ol>
    </nav>

    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 style="font-size: 1.25rem;"><i class="fa-regular fa-file-pdf me-2" style="color: var(--dm-danger);"></i>{{ $document->title }}</h1>
            <p>
                {{ $document->template->name }} · {{ $document->area->name }}
                @php
                    $statusMap = [
                        'approved' => ['Aprobado', 'dm-badge--primary', 'fa-circle-check'],
                        'pending'  => ['Pendiente', 'dm-badge--accent', 'fa-clock'],
                        'rejected' => ['Rechazado', '', 'fa-circle-xmark'],
                    ];
                    [$stLabel, $stClass, $stIcon] = $statusMap[$document->status] ?? ['—', '', 'fa-question'];
                @endphp
                <span class="dm-badge {{ $stClass }} ms-1" @if($document->status==='rejected') style="background: var(--dm-accent-soft); color: var(--dm-danger);" @endif>
                    <i class="fa-solid {{ $stIcon }}"></i> {{ $stLabel }}
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            @if (($canApprove ?? false) && $document->status !== 'approved')
                <button class="btn btn-primary" id="btn-approve-doc">
                    <i class="fa-solid fa-circle-check me-1"></i> Aprobar
                </button>
            @endif
            @if (($canApprove ?? false) && $document->status !== 'rejected')
                <button class="btn btn-outline-danger" id="btn-reject-doc">
                    <i class="fa-solid fa-circle-xmark me-1"></i> Rechazar
                </button>
            @endif
            @if ($canDownload)
                <a href="{{ route('documents.download', $document) }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-download me-1"></i> Descargar
                </a>
            @endif
            @if ($canEdit)
                <button class="btn btn-outline-secondary" id="btn-edit-meta"><i class="fa-solid fa-pen me-1"></i> Editar</button>
                <button class="btn btn-outline-danger" id="btn-delete-doc"><i class="fa-solid fa-trash"></i></button>
            @endif
        </div>
    </div>

    @if ($document->status === 'rejected' && $document->rejection_reason)
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            <i class="fa-solid fa-circle-xmark mt-1"></i>
            <div>
                <strong>Documento rechazado.</strong>
                <div class="small">{{ $document->rejection_reason }}</div>
                @if ($canEdit)
                    <div class="small text-muted mt-1">Edita los metadatos para reenviarlo a revisión (volverá a “Pendiente”).</div>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="dm-card">
                <div class="dm-card__body p-2">
                    <div id="pdf-toolbar" class="d-flex align-items-center gap-2 px-2 py-1 mb-2" style="border-bottom: 1px solid var(--dm-border); flex-wrap: wrap;">
                        <button class="dm-icon-btn" id="pdf-prev" title="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
                        <span class="small mono">Pág. <span id="pdf-page">1</span> / <span id="pdf-count">–</span></span>
                        <button class="dm-icon-btn" id="pdf-next" title="Siguiente"><i class="fa-solid fa-chevron-right"></i></button>

                        {{-- In-PDF find box (yellow highlight) --}}
                        <div class="position-relative ms-2" style="min-width: 200px;">
                            <i class="fa-solid fa-highlighter position-absolute top-50 translate-middle-y ms-2" style="color: var(--dm-accent); font-size:.8rem;"></i>
                            <input id="pdf-find" class="form-control form-control-sm ps-4" placeholder="Resaltar en el PDF…" value="{{ $highlight }}">
                        </div>
                        <span class="small text-muted" id="pdf-find-count"></span>
                        <div class="ms-auto small text-muted" id="pdf-status">Cargando…</div>
                    </div>

                    <div id="pdf-container" style="max-height: 75vh; overflow: auto; background: var(--dm-surface-2); border-radius: var(--dm-radius-sm);">
                        {{-- page wrapper holds canvas + text layer for highlighting --}}
                        <div id="pdf-page-wrap" style="position: relative; margin: 0 auto; width: fit-content;">
                            <canvas id="pdf-canvas"></canvas>
                            <div id="pdf-text-layer" class="pdf-text-layer"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="dm-card">
                <div class="dm-card__body">
                    <h2 style="font-size: 1.05rem; font-weight: 600;">Metadatos</h2>
                    <form id="meta-form">
                        <div class="mb-3" data-view-mode>
                            <dl class="mb-0 small">
                                <dt class="text-muted">Título</dt>
                                <dd class="fw-medium">{{ $document->title }}</dd>
                                @foreach ($document->template->fields as $field)
                                    @php $val = $values[$field->key] ?? null; @endphp
                                    <dt class="text-muted mt-2">{{ $field->label }}</dt>
                                    <dd class="{{ in_array($field->type, ['number','currency']) ? 'mono' : '' }} dm-meta-clickable"
                                        @if ($val) data-highlight-value="{{ $val }}" role="button" tabindex="0"
                                        title="Clic para resaltar en el PDF" @endif>
                                        {{ $val ?? '—' }}
                                        @if ($val)
                                            <i class="fa-solid fa-highlighter dm-meta-hlicon" aria-hidden="true"></i>
                                        @endif
                                    </dd>
                                @endforeach
                            </dl>
                        </div>

                        <div class="d-none" data-edit-mode>
                            <div class="mb-2">
                                <label class="form-label" for="e-title">Título</label>
                                <input class="form-control" id="e-title" name="title" value="{{ $document->title }}" required>
                            </div>
                            @foreach ($document->template->fields as $field)
                                <div class="mb-2">
                                    <label class="form-label" for="e-{{ $field->key }}">
                                        {{ $field->label }}@if ($field->is_required)<span style="color: var(--dm-danger);">*</span>@endif
                                    </label>
                                    @if ($field->type === 'select')
                                        <select class="form-select" id="e-{{ $field->key }}" name="metadata[{{ $field->key }}]">
                                            <option value="">—</option>
                                            @foreach (($field->options ?? []) as $opt)
                                                <option value="{{ $opt }}" @selected(($values[$field->key] ?? '') === $opt)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="input-group input-group-sm">
                                            <input class="form-control {{ in_array($field->type, ['number','currency']) ? 'mono' : '' }}"
                                                   id="e-{{ $field->key }}" name="metadata[{{ $field->key }}]"
                                                   type="{{ $field->type === 'date' ? 'date' : ($field->type === 'number' ? 'number' : 'text') }}"
                                                   value="{{ $values[$field->key] ?? '' }}">
                                            <button type="button" class="btn btn-outline-secondary dm-capture-btn"
                                                    data-target="e-{{ $field->key }}"
                                                    title="Capturar del PDF: clic aquí y selecciona texto en el documento">
                                                <i class="fa-solid fa-crosshairs"></i>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="meta-save">Guardar</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="meta-cancel">Cancelar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- OCR snippet with highlight (fallback for scanned PDFs) --}}
            @if ($document->content && $highlight)
                <div class="dm-card mt-3">
                    <div class="dm-card__body">
                        <span class="dm-badge dm-badge--accent"><i class="fa-solid fa-highlighter me-1"></i> Coincidencia en texto</span>
                        <p class="small mt-2 mb-0" id="ocr-snippet" style="line-height: 1.6; color: var(--dm-text-muted);"></p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('head')
        <style>
            /* PDF.js text layer — invisible text over the canvas, used for highlight */
            .pdf-text-layer {
                position: absolute; inset: 0; overflow: hidden;
                opacity: 1; line-height: 1;
            }
            .pdf-text-layer span {
                position: absolute; white-space: pre; cursor: text;
                color: transparent; transform-origin: 0 0;
            }
            .pdf-text-layer mark {
                background: var(--dm-accent-mark); color: transparent;
                border-radius: 2px;
            }
            /* Clickable metadata values (fill highlight box) */
            .dm-meta-clickable { position: relative; border-radius: 4px; transition: background .12s ease; }
            .dm-meta-clickable[data-highlight-value] { cursor: pointer; padding: 1px 4px; margin-left: -4px; }
            .dm-meta-clickable[data-highlight-value]:hover { background: var(--dm-accent-soft); }
            .dm-meta-hlicon {
                font-size: .7rem; color: var(--dm-accent); margin-left: .35rem;
                opacity: 0; transition: opacity .12s ease;
            }
            .dm-meta-clickable[data-highlight-value]:hover .dm-meta-hlicon { opacity: 1; }

            /* Select-to-fill: visible selection while capturing */
            .pdf-text-layer.dm-capturing { cursor: crosshair; }
            .pdf-text-layer.dm-capturing span { cursor: crosshair; }
            .pdf-text-layer ::selection { background: rgba(245, 158, 11, .45); }
            .dm-capture-btn.active { color: #fff; }
        </style>
    @endpush

    {{-- Server data for resources/js/modules/documents-show.js (loaded via app.js
         page dispatcher; @section('page','documents.show') sets <body data-page>). --}}
    <div id="doc-show-data"
         data-stream-url="{{ route('documents.stream', $document) }}"
         data-update-url="{{ route('documents.update', $document) }}"
         data-destroy-url="{{ route('documents.destroy', $document) }}"
         data-index-url="{{ route('documents.index', ['area' => $document->area_id, 'template' => $document->template_id]) }}"
         @if ($canApprove ?? false)
             data-approve-url="{{ route('documents.approve', $document) }}"
             data-reject-url="{{ route('documents.reject', $document) }}"
             data-can-approve="1"
         @else data-can-approve="0" @endif
         data-can-edit="{{ $canEdit ? '1' : '0' }}"></div>

    @if ($document->content && $highlight)
        {{-- OCR snippet source (kept as a JSON island; body can be large). --}}
        <script id="doc-ocr-snippet" type="application/json">
            @json(['term' => $highlight, 'body' => \Illuminate\Support\Str::limit($document->content->body, 4000)])
        </script>
    @endif
@endsection
