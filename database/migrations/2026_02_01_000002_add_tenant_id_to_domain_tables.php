<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain tables that belong to a tenant. Every row is owned by exactly one
     * tenant; the global scope filters on this column automatically.
     */
    private array $tables = [
        'users',
        'areas',
        'templates',
        'documents',
        'document_metadata',
        'document_contents',
        'ingestion_records',
        'audit_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                // Nullable first so the column can be added to a populated table
                // without a default; a clean system backfills via the seeder.
                $t->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->cascadeOnDelete();
                $t->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
