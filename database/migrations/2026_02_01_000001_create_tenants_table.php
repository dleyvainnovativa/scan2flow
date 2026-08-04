<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Plan link (T-2 fills this in; nullable so T-0 can stand alone).
            $table->foreignId('plan_id')->nullable();

            // Drawdown page balance (T-1 uses this). Pages remaining that the
            // tenant may still ingest. Debited atomically at ingestion.
            $table->unsignedBigInteger('page_balance')->default(0);

            // Premium physical isolation (built later): 'shared' | 'dedicated'.
            $table->string('isolation', 12)->default('shared');

            $table->string('status', 12)->default('active'); // active|suspended
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
