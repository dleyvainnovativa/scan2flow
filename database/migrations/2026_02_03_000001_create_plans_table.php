<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Count-based entitlements. NULL = unlimited.
            $table->unsignedInteger('max_templates')->nullable();
            $table->unsignedInteger('max_areas')->nullable();
            $table->unsignedInteger('max_users')->nullable();

            // Pages granted when this plan is (re)purchased — used by T-4 top-up
            // to know the default bundle size. The live remaining count lives on
            // tenants.page_balance (T-1); this is just the plan's page bundle.
            $table->unsignedBigInteger('page_bundle')->default(0);

            // Pricing (informational for now; billing engine consumes later).
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('MXN');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Now that plans exist, add the FK from tenants.plan_id (T-0 left it a
        // plain nullable column so it could stand alone).
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
        Schema::dropIfExists('plans');
    }
};
