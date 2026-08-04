@extends('layouts.app')

@section('title', 'Plataforma · Tenants')

@section('page', 'platform.tenants')

@section('content')
<div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1>Tenants</h1>
        <p>Administración de clientes de la plataforma.</p>
    </div>
    <button class="btn btn-primary" id="btn-new-tenant">
        <i class="fa-solid fa-plus me-1"></i> Nuevo tenant
    </button>
</div>

<div class="dm-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="text-muted small">
                    <th class="ps-3">Tenant</th>
                    <th>Plan</th>
                    <th>Saldo de páginas</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $t)
                <tr>
                    <td class="ps-3">
                        <a href="{{ route('platform.tenants.show', $t) }}" class="fw-medium text-decoration-none" style="color: var(--dm-text);">
                            <i class="fa-solid fa-building me-2" style="color: var(--dm-primary);"></i>{{ $t->name }}
                        </a>
                        <div class="mono small text-muted">{{ $t->slug }}</div>
                    </td>
                    <td class="small">{{ $t->plan?->name ?? '—' }}</td>
                    <td class="mono">{{ number_format($t->page_balance) }}</td>
                    <td>{{ $t->users_count }}</td>
                    <td>
                        @if ($t->status === 'active')
                        <span class="dm-badge dm-badge--primary"><i class="fa-solid fa-circle-check"></i> Activo</span>
                        @else
                        <span class="dm-badge" style="background: var(--dm-accent-soft); color: var(--dm-danger);"><i class="fa-solid fa-ban"></i> Suspendido</span>
                        @endif
                    </td>
                    <td class="pe-3 text-end">
                        <a href="{{ route('platform.tenants.show', $t) }}" class="btn btn-sm btn-outline-secondary">Gestionar</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No hay tenants todavía.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- New-tenant modal --}}
<div class="modal fade" id="tenant-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--dm-radius);">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo tenant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="nt-name">Nombre del cliente</label>
                    <input class="form-control" id="nt-name" maxlength="120" placeholder="Ej. Despacho López">
                </div>
                <div class="mb-1">
                    <label class="form-label" for="nt-plan">Plan</label>
                    <select class="form-select" id="nt-plan">
                        <option value="">Sin plan (ilimitado)</option>
                        @foreach ($plans as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} · {{ number_format($p->page_bundle) }} págs</option>
                        @endforeach
                    </select>
                    <div class="form-text">El saldo inicial de páginas se toma del plan.</div>
                </div>
                <hr class="my-3">
                <p class="small text-muted mb-2">Administrador inicial del tenant</p>
                <div class="mb-2">
                    <label class="form-label" for="nt-admin-name">Nombre</label>
                    <input class="form-control" id="nt-admin-name" maxlength="120" placeholder="Ej. Ana Torres">
                </div>
                <div class="mb-2">
                    <label class="form-label" for="nt-admin-email">Correo</label>
                    <input class="form-control" id="nt-admin-email" type="email" placeholder="ana@cliente.com">
                </div>
                <div class="mb-1">
                    <label class="form-label" for="nt-admin-pass">Contraseña temporal</label>
                    <input class="form-control" id="nt-admin-pass" type="text" minlength="8" placeholder="mínimo 8 caracteres">
                    <div class="form-text">El administrador podrá cambiarla después.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="nt-save">Crear tenant</button>
            </div>
        </div>
    </div>
</div>

{{-- Server data for the module --}}
<div id="platform-tenants-data"
    data-store-url="{{ route('platform.tenants.store') }}"></div>
@endsection