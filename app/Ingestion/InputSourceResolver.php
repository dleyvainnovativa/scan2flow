<?php

namespace App\Ingestion;

use App\Models\Template;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves a template's INPUT source to a Flysystem disk + a path to scan.
 *
 * This is the input-side equivalent of DocumentStorageService (output side):
 * ingestion no longer touches raw filesystem functions; it asks here for a disk
 * and calls ->files() / ->get() / ->move() on it. Local vs SFTP is a config
 * choice on the template, not a code branch in the ingestion loop.
 */
class InputSourceResolver
{
    /**
     * @return array{disk: Filesystem, path: string, processedPath: string}
     */
    public function resolve(Template $template): array
    {
        if ($template->input_driver === 'sftp' && $template->sftp_connection_id) {
            return $this->resolveSftp($template);
        }

        return $this->resolveLocal($template);
    }

    private function resolveLocal(Template $template): array
    {
        // Root the disk at '/', scan the absolute input_folder_path. Keeps the
        // existing "input_folder_path is an absolute local path" behavior.
        $disk = Storage::build([
            'driver' => 'local',
            'root'   => '/',
        ]);

        $path = ltrim($template->input_folder_path ?? '', '/');
        $processed = trim(($template->input_folder_path ?? ''), '/') . '/processed';

        return ['disk' => $disk, 'path' => $path, 'processedPath' => ltrim($processed, '/')];
    }

    private function resolveSftp(Template $template): array
    {
        $conn = $template->sftpConnection;

        // Build an on-the-fly SFTP disk from the (decrypted) connection config.
        $disk = Storage::build($conn->toDiskConfig());

        // input_folder_path is RELATIVE to the connection's base_path (which is
        // the disk root), e.g. "facturas/entrada".
        $path = trim($template->input_folder_path ?? '', '/');

        // Processed target: the connection's processed_path + the template's
        // subfolder, so moved files mirror their origin.
        $processed = trim($conn->processed_path ?: 'processed', '/') . '/' . $path;

        return ['disk' => $disk, 'path' => $path, 'processedPath' => trim($processed, '/')];
    }
}
