<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enables MATCH...AGAINST on metadata values (searching Proveedor, Folio, etc.).
        // `value` is a VARCHAR(255) from Phase 2, which FULLTEXT supports.
        DB::statement('ALTER TABLE document_metadata ADD FULLTEXT fulltext_value (value)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE document_metadata DROP INDEX fulltext_value');
    }
};
