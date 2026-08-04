<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves an Area — and its ENTIRE subtree — from one tenant to another.
 *
 * An area doesn't travel alone. Reassigning only the area's tenant_id would
 * orphan its templates/documents/metadata/contents under the OLD tenant
 * (split-brain: area in tenant B, its docs still scoped to tenant A → invisible
 * and an isolation risk). So this migrates the whole subtree atomically AND
 * moves the stored files from tenant-{old}/ to tenant-{new}/ on disk.
 *
 * All reads/writes run via TenantContext::withoutScope() because a platform
 * operator works across tenants.
 *
 * Use dryRun() first to see exactly what would move; migrate() to commit.
 */
class TenantMigrationService
{
    public function __construct(
        private TenantContext $context,
        private DocumentStorageService $storage,
    ) {}

    /**
     * Report what a migration WOULD touch, without changing anything.
     * @return array{area:string,from:int,to:int,counts:array,files:int}
     */
    public function dryRun(Area $area, Tenant $to): array
    {
        return $this->context->withoutScope(function () use ($area, $to) {
            $templateIds = DB::table('templates')->where('area_id', $area->id)->pluck('id');
            $documentIds = DB::table('documents')->whereIn('template_id', $templateIds)->pluck('id');

            return [
                'area'   => $area->name,
                'from'   => $area->tenant_id,
                'to'     => $to->id,
                'counts' => [
                    'templates'         => $templateIds->count(),
                    'documents'         => $documentIds->count(),
                    'document_metadata' => DB::table('document_metadata')->whereIn('document_id', $documentIds)->count(),
                    'document_contents' => DB::table('document_contents')->whereIn('document_id', $documentIds)->count(),
                    'permissions'       => DB::table('area_user')->where('area_id', $area->id)->count(),
                ],
                'files'  => $documentIds->count(), // one PDF per doc (+ optional XML)
            ];
        });
    }

    /**
     * Execute the migration. Atomic DB move + best-effort file move.
     *
     * @return array the same shape as dryRun(), plus 'files_moved'
     */
    public function migrate(Area $area, Tenant $to): array
    {
        if ($area->tenant_id === $to->id) {
            throw new \InvalidArgumentException('El área ya pertenece a ese tenant.');
        }

        $plan = $this->dryRun($area, $to);
        $fromTenant = $area->tenant_id;

        return $this->context->withoutScope(function () use ($area, $to, $fromTenant, $plan) {
            // Collect the file moves BEFORE we flip tenant_id, so directoryFor()
            // still resolves old paths. We move files AFTER the DB commit.
            $fileMoves = $this->planFileMoves($area, $to);

            DB::transaction(function () use ($area, $to, $fromTenant) {
                $templateIds = DB::table('templates')->where('area_id', $area->id)->pluck('id');
                $documentIds = DB::table('documents')->whereIn('template_id', $templateIds)->pluck('id');

                // Re-stamp tenant_id down the whole subtree.
                DB::table('areas')->where('id', $area->id)->update(['tenant_id' => $to->id]);
                DB::table('templates')->where('area_id', $area->id)->update(['tenant_id' => $to->id]);

                if ($documentIds->isNotEmpty()) {
                    DB::table('documents')->whereIn('id', $documentIds)->update(['tenant_id' => $to->id]);
                    DB::table('document_metadata')->whereIn('document_id', $documentIds)->update(['tenant_id' => $to->id]);
                    DB::table('document_contents')->whereIn('document_id', $documentIds)->update(['tenant_id' => $to->id]);
                }

                Log::info('Area migrated between tenants', [
                    'area_id' => $area->id, 'from' => $fromTenant, 'to' => $to->id,
                    'templates' => $templateIds->count(), 'documents' => $documentIds->count(),
                ]);
            });

            // DB is committed; now move files. Best-effort — a file failure is
            // logged but doesn't roll back the (already-committed) DB move.
            $moved = $this->executeFileMoves($fileMoves);

            return array_merge($plan, ['files_moved' => $moved]);
        });
    }

    /**
     * Build a list of [oldPath => newPath] by rewriting the tenant segment.
     * Paths look like: {root}/tenant-{old}/{area}/{template}/{file}
     */
    private function planFileMoves(Area $area, Tenant $to): array
    {
        $moves = [];
        $templateIds = DB::table('templates')->where('area_id', $area->id)->pluck('id');
        $docs = DB::table('documents')
            ->whereIn('template_id', $templateIds)
            ->select('id', 'pdf_path', 'xml_path')
            ->get();

        $fromSeg = 'tenant-' . $area->tenant_id . '/';
        $toSeg   = 'tenant-' . $to->id . '/';

        foreach ($docs as $doc) {
            foreach (['pdf_path', 'xml_path'] as $col) {
                $path = $doc->$col ?? null;
                if (! $path) {
                    continue;
                }
                if (str_contains($path, $fromSeg)) {
                    $moves[] = [
                        'doc_id' => $doc->id,
                        'col'    => $col,
                        'old'    => $path,
                        'new'    => str_replace($fromSeg, $toSeg, $path),
                    ];
                }
            }
        }
        return $moves;
    }

    /** Move files on the configured disk and update the stored paths. */
    private function executeFileMoves(array $moves): int
    {
        $disk = Storage::disk(config('documents.disk', 'local'));
        $moved = 0;

        foreach ($moves as $m) {
            try {
                if ($disk->exists($m['old'])) {
                    // Ensure destination dir exists, then move.
                    $dir = dirname($m['new']);
                    if (! $disk->exists($dir)) {
                        $disk->makeDirectory($dir);
                    }
                    $disk->move($m['old'], $m['new']);
                }
                // Update the stored path regardless (points at the new location).
                DB::table('documents')->where('id', $m['doc_id'])->update([$m['col'] => $m['new']]);
                $moved++;
            } catch (\Throwable $e) {
                Log::error('File move failed during tenant migration', [
                    'doc_id' => $m['doc_id'], 'old' => $m['old'], 'new' => $m['new'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $moved;
    }
}
