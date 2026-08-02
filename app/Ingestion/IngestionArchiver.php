<?php

namespace App\Ingestion;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moves source INPUT files into processed/ or failed/ subfolders after handling,
 * when config('ingestion.archive_after') is on. Best-effort: an archive failure
 * is logged but never breaks ingestion.
 */
class IngestionArchiver
{
    private bool $enabled;
    private array $dirs;

    public function __construct()
    {
        $this->enabled = (bool) config('ingestion.archive_after', false);
        $this->dirs    = config('ingestion.archive_dirs', ['success' => 'processed', 'failure' => 'failed']);
    }

    /**
     * Archive a base name's files (PDF/XML) into the outcome subfolder.
     *
     * @param  string  $inputDir  the template's INPUT directory (absolute)
     * @param  array{pdf?:string, xml?:string}  $files  source paths
     * @param  bool  $success
     */
    public function archive(string $inputDir, array $files, bool $success): void
    {
        if (! $this->enabled) {
            return;
        }

        $subdir = $success ? $this->dirs['success'] : $this->dirs['failure'];
        $targetDir = rtrim($inputDir, '/') . '/' . $subdir;

        try {
            if (! is_dir($targetDir) && ! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
                Log::warning('Archive dir not creatable', ['dir' => $targetDir]);
                return;
            }

            foreach (['pdf', 'xml'] as $kind) {
                $src = $files[$kind] ?? null;
                if ($src && is_file($src)) {
                    $dest = $targetDir . '/' . basename($src);
                    // Avoid clobbering: suffix if a file with that name exists.
                    $dest = $this->uniqueDest($dest);
                    @rename($src, $dest);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Archive failed', ['error' => $e->getMessage()]);
        }
    }

    private function uniqueDest(string $dest): string
    {
        if (! file_exists($dest)) {
            return $dest;
        }
        $dir = dirname($dest);
        $name = pathinfo($dest, PATHINFO_FILENAME);
        $ext = pathinfo($dest, PATHINFO_EXTENSION);
        $i = 2;
        do {
            $candidate = "{$dir}/{$name}-{$i}." . $ext;
            $i++;
        } while (file_exists($candidate));
        return $candidate;
    }
}
