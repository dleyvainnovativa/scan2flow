{{-- Sidebar navigation. `dm-nav-link active` + aria-current driven by route matching. --}}
@php
$is = fn ($pattern) => request()->routeIs($pattern);
$cls = fn ($pattern) => $is($pattern) ? 'active' : '';
$isAdmin = auth()->check() && auth()->user()->isAdmin();
@endphp
<aside class="dm-sidebar" aria-label="Navegación principal">
    <div class="dm-sidebar__brand">
        <i class="fa-solid fa-folder-tree"></i>
        <span>Gestor Documental</span>
    </div>

    <nav class="dm-sidebar__nav">
        <div class="dm-nav-label">General</div>
        <a href="{{ route('dashboard') }}" class="dm-nav-link {{ $cls('dashboard') }}" @if($is('dashboard')) aria-current="page" @endif>
            <i class="fa-solid fa-gauge-high"></i> Panel
        </a>
        <a href="{{ route('areas.index') }}" class="dm-nav-link {{ ($is('areas.*') || $is('documents.*')) ? 'active' : '' }}" @if($is('areas.*') || $is('documents.*')) aria-current="page" @endif>
            <i class="fa-solid fa-sitemap"></i> Áreas y documentos
        </a>
        <a href="{{ route('search.index') }}" class="dm-nav-link {{ $cls('search.*') }}" @if($is('search.*')) aria-current="page" @endif>
            <i class="fa-solid fa-magnifying-glass"></i> Búsqueda
        </a>

        @if ($isAdmin)
        <div class="dm-nav-label">Administración</div>
        <a href="{{ route('ingestion.index') }}" class="dm-nav-link {{ $cls('ingestion.*') }}" @if($is('ingestion.*')) aria-current="page" @endif>
            <i class="fa-solid fa-inbox"></i> Captura
        </a>
        <a href="{{ route('templates.index') }}" class="dm-nav-link {{ $cls('templates.*') }}" @if($is('templates.*')) aria-current="page" @endif>
            <i class="fa-solid fa-table-columns"></i> Plantillas
        </a>
        <a href="{{ route('users.index') }}" class="dm-nav-link {{ $cls('users.*') }}" @if($is('users.*')) aria-current="page" @endif>
            <i class="fa-solid fa-users"></i> Usuarios
        </a>
        <a href="{{ route('audit.index') }}" class="dm-nav-link {{ $cls('audit.*') }}" @if($is('audit.*')) aria-current="page" @endif>
            <i class="fa-solid fa-clock-rotate-left"></i> Auditoría
        </a>
        @endif
    </nav>
</aside>