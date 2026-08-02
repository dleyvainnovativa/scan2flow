@extends('layouts.app')

@section('title', 'Captura · Gestor Documental')

@section('content')
    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Captura de documentos</h1>
            <p>Procesa los PDFs y XMLs depositados en las carpetas INPUT de cada plantilla.</p>
        </div>
        <span class="dm-badge {{ $mode === 'queue' ? 'dm-badge--primary' : '' }}">
            <i class="fa-solid {{ $mode === 'queue' ? 'fa-layer-group' : 'fa-bolt' }}"></i>
            Modo: {{ $mode === 'queue' ? 'Cola' : 'Directo' }}
        </span>
    </div>

    {{-- Status count cards --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Completados', $counts['done'], 'fa-circle-check', 'dm-badge--primary'],
                ['Pendientes', $counts['pending'], 'fa-clock', ''],
                ['Fallidos', $counts['failed'], 'fa-circle-exclamation', ''],
                ['Omitidos', $counts['skipped'], 'fa-forward', ''],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $icon, $badge])
            <div class="col-6 col-xl-3">
                <div class="dm-card h-100"><div class="dm-card__body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">{{ $label }}</div>
                        <div class="mono" style="font-size: 1.6rem; font-weight: 600;">{{ $value }}</div>
                    </div>
                    <i class="fa-solid {{ $icon }}" style="font-size: 1.3rem; color: var(--dm-primary);"></i>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="dm-card mb-4">
        <div class="dm-card__body">
            <h2 style="font-size: 1.05rem; font-weight: 600;">Plantillas con carpeta INPUT</h2>
            @forelse ($templates as $tpl)
                <div class="d-flex justify-content-between align-items-center p-2 rounded mb-1"
                     style="border: 1px solid var(--dm-border);" data-template-id="{{ $tpl->id }}">
                    <div>
                        <span class="fw-medium">
                            <i class="fa-solid fa-table-columns me-2" style="color: var(--dm-primary);"></i>{{ $tpl->name }}
                        </span>
                        <span class="text-muted small ms-2">{{ $tpl->area->name }}</span>
                        @if ($tpl->ai_enabled)
                            <span class="dm-badge dm-badge--accent ms-1"><i class="fa-solid fa-wand-magic-sparkles"></i> IA</span>
                        @endif
                        <div class="mono small text-muted mt-1">{{ $tpl->input_folder_path }}</div>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary btn-retry" data-id="{{ $tpl->id }}" title="Reintentar fallidos">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                        <button class="btn btn-sm btn-primary btn-ingest" data-id="{{ $tpl->id }}">
                            <i class="fa-solid fa-play me-1"></i> Procesar ahora
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">Ninguna plantilla tiene carpeta INPUT configurada.</p>
            @endforelse
        </div>
    </div>

    <div class="dm-card">
        <div class="dm-card__body pb-0">
            <h2 style="font-size: 1.05rem; font-weight: 600;">Registros recientes</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Archivo</th><th>Plantilla</th><th>Estado</th><th>Documento</th><th class="pe-3">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $rec)
                        <tr>
                            <td class="ps-3 mono small">{{ $rec->base_name }}</td>
                            <td class="small text-muted">{{ $rec->template?->name }}</td>
                            <td>
                                @php
                                    $badge = ['done'=>'dm-badge--primary','failed'=>'','processing'=>'dm-badge--accent','skipped'=>'','pending'=>''][$rec->status] ?? '';
                                    $lbl = ['done'=>'Completado','failed'=>'Fallido','processing'=>'Procesando','skipped'=>'Omitido','pending'=>'Pendiente'][$rec->status] ?? $rec->status;
                                @endphp
                                <span class="dm-badge {{ $badge }}">{{ $lbl }}</span>
                                @if ($rec->error)
                                    <i class="fa-solid fa-circle-info ms-1" style="color: var(--dm-danger);" title="{{ $rec->error }}"></i>
                                @endif
                            </td>
                            <td class="small">
                                @if ($rec->document)
                                    <a href="{{ route('documents.show', $rec->document) }}">Ver</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="pe-3 small text-muted">{{ $rec->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin registros de captura todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
        window.onDM(function (DM) {
    (function () {
        const { apiPost, toast, withLoading } = window.DM;
        const base = '{{ url('ingestion') }}';

        async function hit(btn, url, reloadDelay = 1200) {
            try {
                await withLoading(btn, async () => {
                    const r = await apiPost(url);
                    toast.success(r.message, 5000);
                    if (!r.queued) setTimeout(() => location.reload(), reloadDelay);
                });
            } catch (err) { toast.error(err.message || 'No se pudo procesar.'); }
        }

        document.querySelectorAll('.btn-ingest').forEach((btn) =>
            btn.addEventListener('click', () => hit(btn, `${base}/${btn.dataset.id}/run`)));
        document.querySelectorAll('.btn-retry').forEach((btn) =>
            btn.addEventListener('click', () => hit(btn, `${base}/${btn.dataset.id}/retry`)));
    })();
    });
    </script>
    @endpush
@endsection
