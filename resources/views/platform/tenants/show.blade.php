@extends('layouts.app')

@section('title', $tenant->name . ' · Plataforma')

@section('page', 'platform.tenant-show')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('platform.tenants.index') }}">Tenants</a></li>
        <li class="breadcrumb-item active">{{ $tenant->name }}</li>
    </ol>
</nav>

<div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1><i class="fa-solid fa-building me-2" style="color: var(--dm-primary);"></i>{{ $tenant->name }}</h1>
        <p>
            <span class="mono">{{ $tenant->slug }}</span>
            @if ($tenant->status === 'active')
            <span class="dm-badge dm-badge--primary ms-1"><i class="fa-solid fa-circle-check"></i> Activo</span>
            @else
            <span class="dm-badge ms-1" style="background: var(--dm-accent-soft); color: var(--dm-danger);"><i class="fa-solid fa-ban"></i> Suspendido</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @if ($tenant->status === 'active')
        <button class="btn btn-outline-danger" id="btn-suspend"><i class="fa-solid fa-ban me-1"></i> Suspender</button>
        @else
        <button class="btn btn-primary" id="btn-activate"><i class="fa-solid fa-circle-check me-1"></i> Activar</button>
        @endif
    </div>
</div>

<div class="row g-3 mb-1">
    {{-- Balance + plan card --}}
    <div class="col-12 col-lg-5">
        <div class="dm-card h-100">
            <div class="dm-card__body">
                <div class="text-muted small mb-1">Saldo de páginas</div>
                <div class="mono" style="font-size: 2rem; font-weight: 600;" id="balance-value">{{ number_format($tenant->page_balance) }}</div>

                <div class="d-flex gap-2 mt-3">
                    <input class="form-control form-control-sm mono" id="topup-pages" type="number" min="1" placeholder="Páginas a agregar" style="max-width: 180px;">
                    <button class="btn btn-sm btn-primary" id="btn-topup"><i class="fa-solid fa-plus me-1"></i> Recargar</button>
                </div>

                <hr class="my-3">

                <div class="text-muted small mb-1">Plan actual</div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="dm-badge dm-badge--primary">{{ $tenant->plan?->name ?? 'Sin plan' }}</span>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <select class="form-select form-select-sm" id="plan-select" style="max-width: 220px;">
                        @foreach (\App\Models\Plan::where('is_active', true)->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}" @selected($tenant->plan_id === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-check form-switch d-flex align-items-center ms-1">
                        <input class="form-check-input" type="checkbox" id="grant-bundle" role="switch">
                        <label class="form-check-label small ms-1" for="grant-bundle">+ páginas del plan</label>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" id="btn-assign-plan">Asignar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Counts card --}}
    <div class="col-12 col-lg-7">
        <div class="dm-card h-100">
            <div class="dm-card__body">
                <div class="text-muted small mb-3">Uso de recursos</div>
                <div class="row g-3">
                    @foreach ([['Usuarios',$counts['users'],'fa-users'],['Áreas',$counts['areas'],'fa-sitemap'],['Plantillas',$counts['templates'],'fa-table-columns'],['Documentos',$counts['documents'],'fa-file-lines']] as [$label,$val,$icon])
                    <div class="col-6 col-md-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid {{ $icon }}" style="color: var(--dm-primary);"></i>
                            <div>
                                <div class="mono" style="font-size: 1.3rem; font-weight: 600;">{{ number_format($val) }}</div>
                                <div class="text-muted small">{{ $label }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-12">
        {{-- Usage history --}}
        <div class="dm-card">
            <div class="dm-card__body pb-0">
                <h2 style="font-size: 1.05rem; font-weight: 600;">Movimientos de páginas</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th class="ps-3">Fecha</th>
                            <th>Concepto</th>
                            <th>Cantidad</th>
                            <th>Saldo después</th>
                            <th class="pe-3">Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usage as $e)
                        <tr>
                            <td class="ps-3 small text-muted">{{ $e->occurred_at?->format('d/m/Y H:i') }}</td>
                            <td class="small">
                                @php $isCredit = $e->quantity > 0; @endphp
                                <span class="dm-badge {{ $isCredit ? 'dm-badge--primary' : '' }}">{{ $e->metric }}</span>
                            </td>
                            <td class="mono {{ $isCredit ? '' : '' }}" style="color: {{ $isCredit ? 'var(--dm-success)' : 'var(--dm-danger)' }};">
                                {{ $isCredit ? '+' : '' }}{{ number_format($e->quantity) }}
                            </td>
                            <td class="mono small">{{ number_format($e->balance_after) }}</td>
                            <td class="pe-3 small text-muted">{{ $e->note }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sin movimientos.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- <div class="dm-card mb-3">
    <div class="dm-card__body">
        <h2 style="font-size: 1.05rem; font-weight: 600;">Reasignaciones (operador)</h2>
        <p class="small text-muted">Mover recursos entre tenants. Las áreas arrastran plantillas, documentos y archivos.</p>

        {{-- Reassign a user --}}
        <div class="d-flex gap-2 align-items-end flex-wrap mb-3">
            <div>
                <label class="form-label small" for="ru-user">ID de usuario</label>
                <input class="form-control form-control-sm mono" id="ru-user" type="number" style="max-width:140px;">
            </div>
            <div>
                <label class="form-label small" for="ru-to">Tenant destino (ID)</label>
                <input class="form-control form-control-sm mono" id="ru-to" type="number" style="max-width:140px;">
            </div>
            <button class="btn btn-sm btn-outline-primary" id="btn-reassign-user">Reasignar usuario</button>
        </div>

        {{-- Migrate an area (dry-run then commit) --}}
        <div class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label small" for="ma-area">ID de área</label>
                <input class="form-control form-control-sm mono" id="ma-area" type="number" style="max-width:140px;">
            </div>
            <div>
                <label class="form-label small" for="ma-to">Tenant destino (ID)</label>
                <input class="form-control form-control-sm mono" id="ma-to" type="number" style="max-width:140px;">
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="btn-preview-area">Previsualizar</button>
            <button class="btn btn-sm btn-outline-danger" id="btn-migrate-area" disabled>Migrar área</button>
        </div>
        <div id="area-preview" class="small text-muted mt-2"></div>
    </div>
</div> -->

<div id="platform-tenant-data"
    data-plan-url="{{ route('platform.tenants.plan', $tenant) }}"
    data-topup-url="{{ route('platform.tenants.topup', $tenant) }}"
    data-status-url="{{ route('platform.tenants.status', $tenant) }}"
    data-reassign-user-url="{{ route('platform.tenants.reassign-user', $tenant) }}"
    data-area-preview-url="{{ route('platform.tenants.area-move.preview', $tenant) }}"
    data-area-move-url="{{ route('platform.tenants.area-move', $tenant) }}"></div>
@endsection