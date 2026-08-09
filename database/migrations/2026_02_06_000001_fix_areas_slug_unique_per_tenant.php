<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Areas slug uniqueness must be PER TENANT, not global. Two tenants can each
     * have a "legal" area. Drop the global unique (areas_slug_unique) and add a
     * composite unique on (tenant_id, slug).
     */
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            // Drop the old global unique on slug.
            // Laravel's default index name for ->unique() on 'slug' is
            // 'areas_slug_unique'. Drop by column so it works regardless.
            $table->dropUnique('areas_slug_unique');
        });

        Schema::table('areas', function (Blueprint $table) {
            // Slug unique WITHIN a tenant.
            $table->unique(['tenant_id', 'slug'], 'areas_tenant_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique('areas_tenant_slug_unique');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->unique('slug', 'areas_slug_unique');
        });
    }
};
