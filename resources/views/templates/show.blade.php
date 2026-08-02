@extends('layouts.app')

@section('title', $template->name . ' · Plantillas')

@section('content')
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('templates.index') }}">Plantillas</a></li>
            <li class="breadcrumb-item"><a href="{{ route('areas.show', $template->area) }}">{{ $template->area->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $template->name }}</li>
        </ol>
    </nav>

    <div class="dm-page-head">
        <h1><i class="fa-solid fa-table-columns me-2" style="color: var(--dm-primary);"></i>{{ $template->name }}</h1>
        <p>{{ $template->description ?: 'Tipo documental en ' . $template->area->name }}</p>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="dm-card">
                <div class="dm-card__body">
                    <h2 style="font-size: 1.05rem; font-weight: 600;">Metadatos</h2>
                    <p class="text-muted small">Campos que se capturan para cada documento de este tipo.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr class="small text-muted">
                                <th>#</th><th>Nombre</th><th>Clave</th><th>Tipo</th><th>Obligatorio</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($template->fields as $i => $f)
                                    <tr>
                                        <td class="text-muted">{{ $i + 1 }}</td>
                                        <td class="fw-medium">{{ $f->label }}</td>
                                        <td class="mono small text-muted">{{ $f->key }}</td>
                                        <td>
                                            @php $labels = ['text'=>'Texto','number'=>'Número','date'=>'Fecha','currency'=>'Moneda','select'=>'Lista']; @endphp
                                            <span class="dm-badge">{{ $labels[$f->type] ?? $f->type }}</span>
                                            @if ($f->type === 'select' && $f->options)
                                                <span class="text-muted small">({{ implode(', ', $f->options) }})</span>
                                            @endif
                                        </td>
                                        <td>{!! $f->is_required ? '<i class="fa-solid fa-check" style="color:var(--dm-success)"></i>' : '<span class="text-muted">—</span>' !!}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="dm-card">
                <div class="dm-card__body">
                    <h2 style="font-size: 1.05rem; font-weight: 600;">Captura</h2>
                    <dl class="mb-0 small">
                        <dt class="text-muted">Carpeta INPUT</dt>
                        <dd class="mono">{{ $template->input_folder_path ?: '—' }}</dd>
                        <dt class="text-muted mt-2">Emparejamiento</dt>
                        <dd>PDF + XML con el mismo nombre</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
