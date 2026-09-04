<?php

namespace App\Console\Commands;

use App\Models\SftpConnection;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verify an SFTP connection works: connect, list the base path.
 *   php artisan sftp:test {connectionId}
 */
class TestSftpConnection extends Command
{
    protected $signature = 'sftp:test {id : SftpConnection id}';
    protected $description = 'Test an SFTP connection (connect + list base path)';

    public function handle(TenantContext $ctx): int
    {
        // Platform/CLI context: read across tenants.
        $conn = $ctx->withoutScope(fn () => SftpConnection::find($this->argument('id')));

        if (! $conn) {
            $this->error('Conexión no encontrada.');
            return self::FAILURE;
        }

        $this->info("Probando {$conn->name} ({$conn->username}@{$conn->host}:{$conn->port}) auth={$conn->auth_type}…");

        try {
            $disk = Storage::build($conn->toDiskConfig());
            $files = $disk->files('');       // list root (= base_path)
            $dirs  = $disk->directories('');

            $this->info('✓ Conexión exitosa.');
            $this->line('  Archivos en base_path: ' . count($files));
            $this->line('  Carpetas en base_path: ' . count($dirs));
            if ($files) {
                $this->line('  Ejemplos: ' . implode(', ', array_slice(array_map('basename', $files), 0, 5)));
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Falló: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
