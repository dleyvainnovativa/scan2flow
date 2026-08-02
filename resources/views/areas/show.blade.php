@extends('layouts.app')

@section('title', $area->name . ' · Áreas')

@section('content')
    @php $isAdmin = auth()->user()->isAdmin(); @endphp

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('areas.index') }}">Áreas</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $area->name }}</li>
        </ol>
    </nav>

    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1><i class="fa-solid fa-sitemap me-2" style="color: var(--dm-primary);"></i>{{ $area->name }}</h1>
            <p>{{ $area->description ?: 'Sin descripción' }}</p>
        </div>
        <a href="{{ route('documents.index', ['area' => $area->id]) }}" class="btn btn-primary">
            <i class="fa-solid fa-file-lines me-1"></i> Ver documentos
        </a>
    </div>

    <div class="row g-4">
        {{-- Templates in this area --}}
        <div class="col-12 col-lg-{{ $isAdmin ? '7' : '12' }}">
            <div class="dm-card">
                <div class="dm-card__body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 style="font-size: 1.05rem; font-weight: 600; margin: 0;">Plantillas</h2>
                        @if ($isAdmin)
                            <a href="{{ route('templates.index', ['area' => $area->id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-plus me-1"></i> Gestionar
                            </a>
                        @endif
                    </div>
                    @forelse ($area->templates as $tpl)
                        <a href="{{ route('templates.show', $tpl) }}"
                           class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none mb-1"
                           style="border: 1px solid var(--dm-border);">
                            <span class="fw-medium" style="color: var(--dm-text);">
                                <i class="fa-solid fa-table-columns me-2" style="color: var(--dm-primary);"></i>{{ $tpl->name }}
                            </span>
                            <span class="dm-badge">{{ $tpl->fields->count() }} metadatos</span>
                        </a>
                    @empty
                        <p class="text-muted small mb-0">No hay plantillas en esta área.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Permission management (admin only) --}}
        @if ($isAdmin)
            <div class="col-12 col-lg-5">
                <div class="dm-card">
                    <div class="dm-card__body">
                        <h2 style="font-size: 1.05rem; font-weight: 600;">Permisos de acceso</h2>
                        <p class="text-muted small">Define qué puede hacer cada usuario en esta área.</p>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="perm-table">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>Usuario</th>
                                        <th class="text-center" title="Ver">Ver</th>
                                        <th class="text-center" title="Descargar">Desc.</th>
                                        <th class="text-center" title="Editar">Edit.</th>
                                        <th class="text-center">Aprobar (QA)</th> 
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $u)
                                        @php $p = $pivots[$u->id]->pivot ?? null; @endphp
                                        <tr data-user-id="{{ $u->id }}">
                                            <td class="small">
                                                <div class="fw-medium">{{ $u->name }}</div>
                                                <div class="text-muted mono" style="font-size: .7rem;">{{ $u->email }}</div>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-view"
                                                       {{ $p && $p->can_view ? 'checked' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-download"
                                                       {{ $p && $p->can_download ? 'checked' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-edit"
                                                       {{ $p && $p->can_edit ? 'checked' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-approve"
                                                    {{ $p && ($p->can_approve ?? false) ? 'checked' : '' }}>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted mt-2 mb-0" style="font-size: .72rem;">
                            <i class="fa-solid fa-circle-info me-1"></i>Descargar y editar incluyen ver. Los cambios se guardan al instante.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($isAdmin)
    @push('scripts')
    <script>
    window.onDM(function (DM) {
        const { apiPost, toast } = DM;
        const setPerm = '{{ url('areas') }}/{{ $area->id }}/permissions';

        document.getElementById('perm-table').addEventListener('change', async (e) => {
            const row = e.target.closest('tr[data-user-id]');
            if (!row) return;
            const view = row.querySelector('.perm-view');
            const download = row.querySelector('.perm-download');
            const edit = row.querySelector('.perm-edit');
            const approve = row.querySelector('.perm-approve');

            if ((download.checked || edit.checked) && !view.checked) view.checked = true;
    // approve/download/edit all imply view
    if ((download.checked || edit.checked || approve.checked) && !view.checked) view.checked = true;
            try {
                const r = await apiPost(setPerm, {
        user_id: row.dataset.userId,
        can_view: view.checked,
        can_download: download.checked,
        can_edit: edit.checked,
        can_approve: approve.checked,     // >>> ADD
    });
                toast.success(r.message, 2000);
            } catch (err) { toast.error(err.message); }

            
        });
    });
    </script>
    @endpush
    @endif
@endsection
