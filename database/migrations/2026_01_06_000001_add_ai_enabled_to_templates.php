<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Opt-in per template: AI structuring only runs where enabled, so
            // CFDI-only templates never incur an AI call.
            $table->boolean('ai_enabled')->default(false)->after('naming_rule');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('ai_enabled');
        });
    }
};
