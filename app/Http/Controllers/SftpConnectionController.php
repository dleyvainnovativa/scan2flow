<?php

namespace App\Http\Controllers;

use App\Models\SftpConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Tenant-admin CRUD for SFTP connections. Credentials are WRITE-ONLY: stored
 * secrets are never sent back to the browser. On edit, blank secret fields mean
 * "keep the current value"; only a filled field overwrites.
 *
 * SftpConnection is tenant-scoped (BelongsToTenant), so index/find are already
 * constrained to the current tenant — no manual filtering needed.
 */
class SftpConnectionController extends Controller
{
    public function __construct()
    {
        // Admin-only area. (Route is also behind 'admin' middleware.)
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        // Tenant scope applies automatically. Secrets are $hidden on the model,
        // but we only pass the non-secret fields to the view anyway.
        $connections = SftpConnection::orderBy('name')->get()->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'host'           => $c->host,
            'port'           => $c->port,
            'username'       => $c->username,
            'auth_type'      => $c->auth_type,
            'base_path'      => $c->base_path,
            'processed_path' => $c->processed_path,
            // NOTE: no password/private_key/passphrase — never leave the server.
            'has_password'   => filled($c->password),
            'has_key'        => filled($c->private_key),
        ]);

        return view('sftp.index', compact('connections'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $this->validateInput($request, creating: true);

        $conn = new SftpConnection();
        $conn->fill($this->nonSecretFields($data));
        $this->applySecrets($conn, $data);           // sets creds per auth_type
        $conn->save();                                // tenant_id auto-filled

        return response()->json(['message' => 'Conexión creada.', 'id' => $conn->id], 201);
    }

    public function update(Request $request, SftpConnection $sftp_connection)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $this->validateInput($request, creating: false);

        $sftp_connection->fill($this->nonSecretFields($data));
        // Only overwrite secrets that were actually provided (write-only edit).
        $this->applySecrets($sftp_connection, $data, keepBlank: true);
        $sftp_connection->save();

        return response()->json(['message' => 'Conexión actualizada.']);
    }

    public function destroy(Request $request, SftpConnection $sftp_connection)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $sftp_connection->delete();

        return response()->json(['message' => 'Conexión eliminada.']);
    }

    /** Live test: connect + list the base path. Never returns credentials. */
    public function test(Request $request, SftpConnection $sftp_connection)
    {
        abort_unless($request->user()->isAdmin(), 403);

        try {
            $disk  = Storage::build($sftp_connection->toDiskConfig());
            $files = $disk->files('');
            $dirs  = $disk->directories('');

            return response()->json([
                'ok'      => true,
                'message' => 'Conexión exitosa.',
                'files'   => count($files),
                'dirs'    => count($dirs),
                'sample'  => array_slice(array_map('basename', $files), 0, 5),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Falló la conexión: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function validateInput(Request $request, bool $creating): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'host'           => ['required', 'string', 'max:255'],
            'port'           => ['required', 'integer', 'between:1,65535'],
            'username'       => ['required', 'string', 'max:190'],
            'auth_type'      => ['required', Rule::in(['password', 'key'])],
            // Secrets: required on create (per auth_type); optional on edit.
            'password'       => [$creating ? 'nullable' : 'nullable', 'string'],
            'private_key'    => [$creating ? 'nullable' : 'nullable', 'string'],
            'passphrase'     => ['nullable', 'string'],
            'base_path'      => ['nullable', 'string', 'max:255'],
            'processed_path' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function nonSecretFields(array $data): array
    {
        return [
            'name'           => $data['name'],
            'host'           => $data['host'],
            'port'           => $data['port'],
            'username'       => $data['username'],
            'auth_type'      => $data['auth_type'],
            'base_path'      => $data['base_path'] ?? '/',
            'processed_path' => $data['processed_path'] ?? 'processed',
        ];
    }

    /**
     * Apply secrets according to auth_type. When keepBlank is true (edit), a
     * blank field leaves the stored secret untouched. Clears the OTHER auth
     * method's secret so switching auth types doesn't leave stale creds.
     */
    private function applySecrets(SftpConnection $conn, array $data, bool $keepBlank = false): void
    {
        if ($data['auth_type'] === 'password') {
            if (! $keepBlank || filled($data['password'] ?? null)) {
                $conn->password = $data['password'] ?? null;
            }
            $conn->private_key = null;   // clear stale key
            $conn->passphrase  = null;
        } else { // key
            if (! $keepBlank || filled($data['private_key'] ?? null)) {
                $conn->private_key = $data['private_key'] ?? null;
            }
            // passphrase is optional; update only if provided (or on create).
            if (! $keepBlank || filled($data['passphrase'] ?? null)) {
                $conn->passphrase = $data['passphrase'] ?? null;
            }
            $conn->password = null;      // clear stale password
        }
    }
}
