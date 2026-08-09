@extends('layouts.app')

@section('title', 'Documentos · Gestor Documental')

@section('content')
<div class="dm-page-head">
    <h1>Documentos</h1>
    <p>Busca en metadatos y en el texto (OCR) de los documentos.</p>
</div>

<form method="GET" action="{{ route('search.index') }}" class="dm-card mb-4">
    <div class="dm-card__body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-6">
                <label class="form-label" for="q">Búsqueda por contentido</label>
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-3" style="color: var(--dm-text-soft);"></i>
                    <input class="form-control ps-5" id="q" name="q" value="{{ $q }}"
                        placeholder="Ej. proveedor, folio, o una palabra del documento" autofocus>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label" for="area_id">Tipo de documento</label>
                <select class="form-select" id="area_id" name="area_id">
                    <option value="">Todas</option>
                    @foreach ($areas as $a)
                    <option value="{{ $a->id }}" @selected(($filters['area_id'] ?? null)==$a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
            </div>
        </div>
    </div>
</form>

@if ($q !== '')
<p class="text-muted small mb-3">
    {{ $results->count() }} resultado(s) para «<span class="fw-medium">{{ $q }}</span>»
</p>

@forelse ($results as $r)
<a href="{{ route('documents.show', $r->document) }}?q={{ urlencode($q) }}"
    class="dm-card dm-card--hover d-block text-decoration-none mb-2">
    <div class="dm-card__body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="fw-medium mb-1" style="color: var(--dm-text);">
                    <i class="fa-regular fa-file-pdf me-2" style="color: var(--dm-danger);"></i>{{ $r->document->title }}
                </div>
                <div class="text-muted small mb-2">
                    {{ $r->document->area->name }} · {{ $r->document->template->name }}
                </div>
                @if ($r->snippet)
                <div class="small" style="color: var(--dm-text-muted); line-height: 1.6;">{!! $r->snippet !!}</div>
                @endif
            </div>
            <div class="text-end flex-shrink-0">
                @php $labels = ['metadata' => 'Metadatos', 'ocr' => 'OCR', 'both' => 'Metadatos + OCR']; @endphp
                <span class="dm-badge dm-badge--primary">{{ $labels[$r->matchedIn] ?? 'Coincidencia' }}</span>
            </div>
        </div>
    </div>
</a>
@empty
<div class="dm-card">
    <div class="dm-card__body text-center text-muted py-5">
        <i class="fa-solid fa-magnifying-glass mb-2" style="font-size: 1.5rem;"></i>
        <p class="mb-0">Sin resultados. Prueba otro término o quita filtros.</p>
    </div>
</div>
@endforelse
@endif
@endsection