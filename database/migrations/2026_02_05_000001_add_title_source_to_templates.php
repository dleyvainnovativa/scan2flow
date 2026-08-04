<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // How the document title is set at ingestion:
            //   'derived'  → from CFDI metadata (folio/uuid/serie), fallback filename
            //   'original' → the source PDF's filename as-is
            $table->string('title_source', 20)->default('derived')->after('naming_rule');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('title_source');
        });
    }
};
