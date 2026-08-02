@extends('layouts.app')

@section('title', 'Panel · Gestor Documental')

@section('content')
<div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1>Panel</h1>
        <p>Resumen de tu gestor documental.</p>
    </div>
    <a href="{{ route('search.index') }}" class="btn btn-primary">
        <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar documentos
    </a>
</div>

{{-- Stat cards --}}
<div class="row g-3">
    @php
    $cards = [
    ['Áreas', $stats['areas'], 'fa-sitemap', route('areas.index')],
    ['Plantillas', $stats['templates'], 'fa-table-columns', $isAdmin ? route('templates.index') : null],
    ['Documentos', $stats['documents'], 'fa-file-lines', route('areas.index')],
    ['Páginas', $stats['pages'], 'fa-copy', null], ];
    if (!is_null($stats['users'])) {
    $cards[] = ['Usuarios', $stats['users'], 'fa-users', route('users.index')];
    }
    @endphp
    @foreach ($cards as [$label, $value, $icon, $link])
    <div class="col-12 col-sm-6 col-xl-3">
        @if ($link)
        <a href="{{ $link }}" class="text-decoration-none">
            @endif
            <div class="dm-card dm-card--hover h-100">
                <div class="dm-card__body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">{{ $label }}</div>
                        <div class="mono" style="font-size: 1.75rem; font-weight: 600; color: var(--dm-text);">{{ $value }}</div>
                    </div>
                    <i class="fa-solid {{ $icon }}" style="font-size: 1.4rem; color: var(--dm-primary);"></i>
                </div>
            </div>
            @if ($link)
        </a>
        @endif
    </div>
    @endforeach
</div>

<div class="row g-4 mt-1">
    {{-- Recent documents --}}
    <div class="col-12 col-lg-7">
        <div class="dm-card h-100">
            <div class="dm-card__body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 style="font-size: 1.05rem; font-weight: 600; margin: 0;">Documentos recientes</h2>
                    <a href="{{ route('areas.index') }}" class="small">Ver todo</a>
                </div>
                @forelse ($recentDocuments as $doc)
                <a href="{{ route('documents.show', $doc) }}"
                    class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none mb-1"
                    style="border: 1px solid var(--dm-border);">
                    <span class="d-flex align-items-center gap-2" style="min-width: 0;">
                        <i class="fa-regular fa-file-pdf" style="color: var(--dm-danger);"></i>
                        <span class="text-truncate" style="color: var(--dm-text);">{{ $doc->title }}</span>
                    </span>
                    <span class="dm-badge flex-shrink-0">{{ $doc->template->name }}</span>
                </a>
                @empty
                <div class="text-center text-muted py-4">
                    <i class="fa-regular fa-folder-open mb-2" style="font-size: 1.4rem;"></i>
                    <p class="mb-0 small">Aún no hay documentos. Los documentos aparecerán aquí conforme se capturen.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Areas overview --}}
    <div class="col-12 col-lg-5">
        <div class="dm-card h-100">
            <div class="dm-card__body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 style="font-size: 1.05rem; font-weight: 600; margin: 0;">Áreas</h2>
                    <a href="{{ route('areas.index') }}" class="small">Ver todo</a>
                </div>
                @forelse ($areas as $area)
                <a href="{{ route('areas.show', $area) }}"
                    class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none mb-1"
                    style="border: 1px solid var(--dm-border);">
                    <span style="color: var(--dm-text);">
                        <i class="fa-solid fa-sitemap me-2" style="color: var(--dm-primary);"></i>{{ $area->name }}
                    </span>
                    <span class="d-flex gap-1 flex-shrink-0">
                        <span class="dm-badge" title="Plantillas">{{ $area->templates_count }}</span>
                        <span class="dm-badge dm-badge--primary" title="Documentos">{{ $area->documents_count }}</span>
                    </span>
                </a>
                @empty
                <div class="text-center text-muted py-4">
                    <i class="fa-solid fa-sitemap mb-2" style="font-size: 1.4rem;"></i>
                    <p class="mb-0 small">
                        @if ($isAdmin)
                        Crea tu primera área para empezar.
                        @else
                        No tienes áreas asignadas. Contacta al administrador.
                        @endif
                    </p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection