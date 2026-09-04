{{-- Sidebar navigation. `dm-nav-link active` + aria-current driven by route matching. --}}
@php
$is = fn ($pattern) => request()->routeIs($pattern);
$cls = fn ($pattern) => $is($pattern) ? 'active' : '';
$isAdmin = auth()->check() && auth()->user()->isAdmin();
$isAdminPlatform = auth()->check() && auth()->user()->isPlatformAdmin();
@endphp
<aside class="dm-sidebar" aria-label="Navegación principal">
    <div class="dm-sidebar__brand">
        <i class="fa-solid fa-folder-tree"></i>
        <span>Gestor Documental</span>
    </div>

    <nav class="dm-sidebar__nav">
        @if ($isAdminPlatform)
        {{-- Platform operators live above tenancy: platform-only nav. --}}
        <div class="dm-nav-label">Plataforma</div>
        <a href="{{ route('platform.dashboard') }}" class="dm-nav-link {{ $cls('platform.dashboard') }}" @if($is('platform.dashboard')) aria-current="page" @endif>
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="{{ route('platform.tenants.index') }}" class="dm-nav-link {{ ($is('platform.tenants.*')) ? 'active' : '' }}" @if($is('platform.tenants.*')) aria-current="page" @endif>
            <i class="fa-solid fa-building"></i> Tenants
        </a>
        @else
        {{-- Regular tenant users (admins + members). --}}
        <div class="dm-nav-label">Administración</div>
        <a href="{{ route('dashboard') }}" class="dm-nav-link {{ $cls('dashboard') }}" @if($is('dashboard')) aria-current="page" @endif>
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="{{ route('areas.index') }}" class="dm-nav-link {{ ($is('areas.*') || $is('documents.*')) ? 'active' : '' }}" @if($is('areas.*') || $is('documents.*')) aria-current="page" @endif>
            <i class="fa-solid fa-sitemap"></i> Bóvedas
        </a>
        <a href="{{ route('users.index') }}" class="dm-nav-link {{ $cls('users.*') }}" @if($is('users.*')) aria-current="page" @endif>
            <i class="fa-solid fa-users"></i> Usuarios
        </a>
        <a href="{{ route('audit.index') }}" class="dm-nav-link {{ $cls('audit.*') }}" @if($is('audit.*')) aria-current="page" @endif>
            <i class="fa-solid fa-clock-rotate-left"></i> Auditoría
        </a>

        @if ($isAdmin)
        <div class="dm-nav-label">Documentación</div>
        <a href="{{ route('templates.index') }}" class="dm-nav-link {{ $cls('templates.*') }}" @if($is('templates.*')) aria-current="page" @endif>
            <i class="fa-solid fa-table-columns"></i> Plantillas de captura
        </a>
        <a href="{{ route('ingestion.index') }}" class="dm-nav-link {{ $cls('ingestion.*') }}" @if($is('ingestion.*')) aria-current="page" @endif>
            <i class="fa-solid fa-inbox"></i> Procesamiento
        </a>
        <a href="{{ route('search.index') }}" class="dm-nav-link {{ $cls('search.*') }}" @if($is('search.*')) aria-current="page" @endif>
            <i class="fa-solid fa-magnifying-glass"></i> Búsqueda de Documentos
        </a>
        <a href="{{ route('sftp-connections.index') }}"
            class="dm-nav-link {{ $cls('sftp-connections.*') }}"
            @if($is('sftp-connections.*')) aria-current="page" @endif>
            <i class="fa-solid fa-server"></i> Conexiones SFTP
        </a>
        @endif
        @endif
    </nav>
</aside>