<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform operator (runs the SaaS). Orthogonal to the tenant 'role'
            // (admin|member). A platform admin has tenant_id = NULL and operates
            // above tenancy via TenantContext::withoutScope().
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
