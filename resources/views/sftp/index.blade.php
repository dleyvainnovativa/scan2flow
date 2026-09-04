@extends('layouts.app')

@section('title', 'Conexiones SFTP')

@section('page', 'sftp.index')

@section('content')
    <div class="dm-page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1><i class="fa-solid fa-server me-2" style="color: var(--dm-primary);"></i>Conexiones SFTP</h1>
            <p>Orígenes remotos para la captura de documentos.</p>
        </div>
        <button class="btn btn-primary" id="btn-new-sftp">
            <i class="fa-solid fa-plus me-1"></i> Nueva conexión
        </button>
    </div>

    <div class="dm-card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">Nombre</th>
                        <th>Servidor</th>
                        <th>Usuario</th>
                        <th>Auth</th>
                        <th>Ruta base</th>
                        <th class="pe-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="sftp-rows">
                    @forelse ($connections as $c)
                        <tr data-id="{{ $c['id'] }}"
                            data-json='@json($c)'
                            data-update-url="{{ route('sftp-connections.update', $c['id']) }}"
                            data-destroy-url="{{ route('sftp-connections.destroy', $c['id']) }}"
                            data-test-url="{{ route('sftp-connections.test', $c['id']) }}">
                            <td class="ps-3 fw-medium">{{ $c['name'] }}</td>
                            <td class="mono small">{{ $c['host'] }}:{{ $c['port'] }}</td>
                            <td class="small">{{ $c['username'] }}</td>
                            <td><span class="dm-badge">{{ $c['auth_type'] === 'key' ? 'Llave' : 'Contraseña' }}</span></td>
                            <td class="mono small text-muted">{{ $c['base_path'] }}</td>
                            <td class="pe-3 text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-action="test" title="Probar conexión">
                                    <i class="fa-solid fa-plug-circle-check"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" data-action="edit">Editar</button>
                                <button class="btn btn-sm btn-outline-danger" data-action="delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay conexiones. Crea una para capturar desde SFTP.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create/Edit modal --}}
    <div class="modal fade" id="sftp-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--dm-radius);">
                <div class="modal-header">
                    <h5 class="modal-title" id="sftp-modal-title">Nueva conexión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sf-id">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label" for="sf-name">Nombre</label>
                            <input class="form-control" id="sf-name" maxlength="120" placeholder="SFTP de Cliente X">
                        </div>
                        <div class="col-8">
                            <label class="form-label" for="sf-host">Host</label>
                            <input class="form-control mono" id="sf-host" placeholder="sftp.cliente.com">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="sf-port">Puerto</label>
                            <input class="form-control mono" id="sf-port" type="number" value="22" min="1" max="65535">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sf-username">Usuario</label>
                            <input class="form-control mono" id="sf-username" placeholder="usuario">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sf-auth">Autenticación</label>
                            <select class="form-select" id="sf-auth">
                                <option value="password">Contraseña</option>
                                <option value="key">Llave privada (SSH)</option>
                            </select>
                        </div>

                        {{-- Password field --}}
                        <div class="col-12" data-auth-block="password">
                            <label class="form-label" for="sf-password">Contraseña</label>
                            <input class="form-control" id="sf-password" type="password" autocomplete="new-password" placeholder="••••••••">
                            <div class="form-text" data-edit-hint hidden>Deja en blanco para conservar la contraseña actual.</div>
                        </div>

                        {{-- Key fields --}}
                        <div class="col-12" data-auth-block="key" hidden>
                            <label class="form-label" for="sf-key">Llave privada</label>
                            <textarea class="form-control mono" id="sf-key" rows="4" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                            <div class="form-text" data-edit-hint hidden>Deja en blanco para conservar la llave actual.</div>
                            <label class="form-label mt-2" for="sf-passphrase">Frase de la llave (opcional)</label>
                            <input class="form-control" id="sf-passphrase" type="password" autocomplete="new-password" placeholder="opcional">
                        </div>

                        <div class="col-6">
                            <label class="form-label" for="sf-base">Ruta base</label>
                            <input class="form-control mono" id="sf-base" value="/" placeholder="/">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="sf-processed">Carpeta procesados</label>
                            <input class="form-control mono" id="sf-processed" value="processed" placeholder="processed">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" id="sf-save">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="sftp-data" data-store-url="{{ route('sftp-connections.store') }}"></div>
@endsection
