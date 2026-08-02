@extends('layouts.app')

@section('title', 'Auditoría · Gestor Documental')

@section('content')
    <div class="dm-page-head">
        <h1>Auditoría</h1>
        <p>Registro de acciones: quién vio, descargó, editó o eliminó documentos.</p>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="dm-card mb-3">
        <div class="dm-card__body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="action" style="font-size:.72rem;">Acción</label>
                    <select class="form-select form-select-sm" id="action" name="action">
                        <option value="">Todas</option>
                        @php $labels = ['viewed'=>'Vio','downloaded'=>'Descargó','edited'=>'Editó','deleted'=>'Eliminó','uploaded'=>'Cargó','login'=>'Inició sesión']; @endphp
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected(request('action') === $a)>{{ $labels[$a] ?? $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
                    @if (request('action'))
                        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th class="pe-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="ps-3 mono small text-muted">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="small fw-medium">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td>
                                @php
                                    $badge = ['viewed'=>'','downloaded'=>'dm-badge--primary','edited'=>'dm-badge--accent','deleted'=>'','uploaded'=>'dm-badge--primary','login'=>''][$log->action] ?? '';
                                    $lbl = ['viewed'=>'Vio','downloaded'=>'Descargó','edited'=>'Editó','deleted'=>'Eliminó','uploaded'=>'Cargó','login'=>'Inició sesión'][$log->action] ?? $log->action;
                                @endphp
                                <span class="dm-badge {{ $badge }}">{{ $lbl }}</span>
                            </td>
                            <td class="small text-muted">{{ $log->summary }}</td>
                            <td class="pe-3 mono small text-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
