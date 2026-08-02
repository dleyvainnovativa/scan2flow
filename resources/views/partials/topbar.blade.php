{{-- Topbar: mobile burger, global search, theme toggle, user menu. --}}
<header class="dm-topbar">
    <button class="dm-icon-btn dm-burger" type="button" aria-label="Abrir menú">
        <i class="fa-solid fa-bars"></i>
    </button>

    <form class="flex-grow-1" style="max-width: 480px;" method="GET" action="{{ route('search.index') }}">
        <div class="position-relative">
            <i class="fa-solid fa-magnifying-glass position-absolute top-50 translate-middle-y ms-3"
               style="color: var(--dm-text-soft);"></i>
            <input type="search" name="q" class="form-control ps-5" placeholder="Buscar documentos…"
                   aria-label="Buscar" value="{{ request()->routeIs('search.*') ? request('q') : '' }}">
        </div>
    </form>

    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="dm-icon-btn" type="button" data-theme-toggle aria-label="Cambiar tema">
            <i class="fa-solid fa-moon" data-theme-icon></i>
        </button>

        <div class="dropdown">
            <button class="dm-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                <i class="fa-solid fa-user"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @auth
                    <li>
                        <span class="dropdown-item-text">
                            <div class="fw-medium">{{ auth()->user()->name }}</div>
                            <div class="small text-muted mono">{{ auth()->user()->email }}</div>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                @endauth
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Salir
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
