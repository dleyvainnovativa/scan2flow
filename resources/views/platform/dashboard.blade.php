@extends('layouts.app')

@section('title', 'Plataforma · Panel')

@section('page', 'platform.dashboard')

@section('content')
    <div class="dm-page-head">
        <h1>Panel de plataforma</h1>
        <p>Métricas de todos los tenants de la plataforma.</p>
    </div>

    {{-- Top stat cards --}}
    <div class="row g-3 mb-1">
        <div class="col-6 col-lg-3">
            <div class="dm-card h-100"><div class="dm-card__body">
                <div class="text-muted small mb-1">Tenants</div>
                <div class="mono" style="font-size: 1.8rem; font-weight: 600;">{{ number_format($tenantCounts['total']) }}</div>
                <div class="small">
                    <span style="color: var(--dm-success);">{{ $tenantCounts['active'] }} activos</span>
                    @if ($tenantCounts['suspended'] > 0)
                        · <span style="color: var(--dm-danger);">{{ $tenantCounts['suspended'] }} suspendidos</span>
                    @endif
                </div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card h-100"><div class="dm-card__body">
                <div class="text-muted small mb-1">Páginas consumidas</div>
                <div class="mono" style="font-size: 1.8rem; font-weight: 600;">{{ number_format($pagesConsumed) }}</div>
                <div class="small text-muted">total ingestadas</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card h-100"><div class="dm-card__body">
                <div class="text-muted small mb-1">Páginas vendidas</div>
                <div class="mono" style="font-size: 1.8rem; font-weight: 600;">{{ number_format($pagesSold) }}</div>
                <div class="small text-muted">recargas acumuladas</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="dm-card h-100"><div class="dm-card__body">
                <div class="text-muted small mb-1">Saldo vigente</div>
                <div class="mono" style="font-size: 1.8rem; font-weight: 600;">{{ number_format($pagesOutstanding) }}</div>
                <div class="small text-muted">páginas sin usar</div>
            </div></div>
        </div>
        {{-- Revenue proxy --}}
        @if ($revenueByCurrency->isNotEmpty())
        <div class="col-6 col-lg-12">

            <div class="dm-card mb-3"><div class="dm-card__body">
                <div class="text-muted small mb-2">Ingreso recurrente estimado (planes activos)</div>
                <div class="d-flex gap-4 flex-wrap">
                    @foreach ($revenueByCurrency as $rev)
                        <div class="mono" style="font-size: 1.4rem; font-weight: 600;">
                            {{ number_format($rev->cents / 100, 2) }} <span class="text-muted small">{{ $rev->currency }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Suma del precio de plan de cada tenant activo. Aproximado.</div>
            </div></div>
        </div>
        @endif
    </div>


    <div class="row g-3">
        {{-- Recent signups --}}
        <div class="col-12 col-lg-6">
            <div class="dm-card h-100">
                <div class="dm-card__body pb-0"><h2 style="font-size: 1.05rem; font-weight: 600;">Tenants recientes</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr class="text-muted small"><th class="ps-3">Tenant</th><th>Plan</th><th>Alta</th><th class="pe-3">Estado</th></tr></thead>
                        <tbody>
                            @forelse ($recentTenants as $t)
                                <tr>
                                    <td class="ps-3">
                                        <a href="{{ route('platform.tenants.show', $t) }}" class="text-decoration-none fw-medium" style="color: var(--dm-text);">{{ $t->name }}</a>
                                    </td>
                                    <td class="small">{{ $t->plan?->name ?? '—' }}</td>
                                    <td class="small text-muted">{{ $t->created_at?->format('d/m/Y') }}</td>
                                    <td class="pe-3">
                                        @if ($t->status === 'active')
                                            <span class="dm-badge dm-badge--primary">Activo</span>
                                        @else
                                            <span class="dm-badge" style="background: var(--dm-accent-soft); color: var(--dm-danger);">Suspendido</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Sin tenants.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent activity --}}
        <div class="col-12 col-lg-6">
            <div class="dm-card h-100">
                <div class="dm-card__body pb-0"><h2 style="font-size: 1.05rem; font-weight: 600;">Actividad reciente</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr class="text-muted small"><th class="ps-3">Tenant</th><th>Concepto</th><th class="pe-3">Cantidad</th></tr></thead>
                        <tbody>
                            @forelse ($recentActivity as $e)
                                <tr>
                                    <td class="ps-3 small">{{ $e->tenant?->name ?? '—' }}</td>
                                    <td class="small"><span class="dm-badge">{{ $e->metric }}</span></td>
                                    <td class="pe-3 mono small" style="color: {{ $e->quantity > 0 ? 'var(--dm-success)' : 'var(--dm-danger)' }};">
                                        {{ $e->quantity > 0 ? '+' : '' }}{{ number_format($e->quantity) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">Sin actividad.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Plan distribution --}}
    <div class="dm-card mt-3">
        <div class="dm-card__body pb-0"><h2 style="font-size: 1.05rem; font-weight: 600;">Distribución por plan</h2></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr class="text-muted small"><th class="ps-3">Plan</th><th class="pe-3">Tenants</th></tr></thead>
                <tbody>
                    @foreach ($planDistribution as $row)
                        <tr>
                            <td class="ps-3">{{ $row->plan_name }}</td>
                            <td class="pe-3 mono">{{ $row->tenants }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
