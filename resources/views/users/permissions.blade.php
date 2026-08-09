@extends('layouts.app')

@section('title', 'Permisos · ' . $user->name)

@section('page', 'users.permissions')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
            <li class="breadcrumb-item active">Permisos</li>
        </ol>
    </nav>

    <div class="dm-page-head">
        <h1><i class="fa-solid fa-shield-halved me-2" style="color: var(--dm-primary);"></i>Permisos de {{ $user->name }}</h1>
        <p>{{ $user->email }} · acceso por área. Los cambios se guardan al instante.</p>
    </div>

    @if ($user->isAdmin())
        <div class="dm-card mb-3"><div class="dm-card__body">
            <i class="fa-solid fa-circle-info me-1" style="color: var(--dm-primary);"></i>
            Este usuario es <strong>administrador</strong>: tiene acceso completo a todas las áreas independientemente de esta matriz.
        </div></div>
    @endif

    {{-- Bulk actions --}}
    <div class="dm-card mb-3"><div class="dm-card__body d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted me-2">Acciones rápidas:</span>
        <button class="btn btn-sm btn-outline-primary" data-bulk="can_view" data-value="1">
            <i class="fa-solid fa-eye me-1"></i> Ver en todas
        </button>
        <button class="btn btn-sm btn-outline-secondary" data-bulk="can_download" data-value="1">
            Descargar en todas
        </button>
        <button class="btn btn-sm btn-outline-danger ms-auto" data-bulk="revoke-all" data-value="0">
            <i class="fa-solid fa-ban me-1"></i> Revocar todo
        </button>
    </div></div>

    {{-- Permission matrix --}}
    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="perm-matrix">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Área</th>
                        <th class="text-center">Ver</th>
                        <th class="text-center">Descargar</th>
                        <th class="text-center">Editar</th>
                        <th class="text-center pe-3">Aprobar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        @php $p = $pivots[$area->id] ?? null; @endphp
                        <tr data-area-id="{{ $area->id }}"
                            data-perm-url="{{ route('areas.permissions', $area) }}">
                            <td class="ps-3">
                                <span class="fw-medium">{{ $area->name }}</span>
                                @if ($area->description)
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($area->description, 60) }}</div>
                                @endif
                            </td>
                            @foreach (['can_view' => 'view', 'can_download' => 'download', 'can_edit' => 'edit', 'can_approve' => 'approve'] as $flag => $short)
                                <td class="text-center @if($flag === 'can_approve') pe-3 @endif">
                                    <input type="checkbox" class="form-check-input perm-box"
                                           data-flag="{{ $flag }}"
                                           @checked($p[$flag] ?? false)
                                           @if($user->isAdmin()) disabled title="Los administradores tienen acceso completo" @endif>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay áreas en este tenant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="user-perms-data"
         data-user-id="{{ $user->id }}"
         data-bulk-url="{{ route('users.permissions.bulk', $user) }}"></div>
@endsection
