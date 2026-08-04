<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // What happened: 'pages_ingested' (debit), 'pages_topup' (credit),
            // future: 'pages_adjustment', etc.
            $table->string('metric', 40);

            // Signed quantity: negative = debit, positive = credit. Sum over a
            // tenant's rows reconciles against the tenants.page_balance counter.
            $table->integer('quantity');

            // Running balance AFTER this event (audit convenience).
            $table->bigInteger('balance_after')->nullable();

            // Optional link to what caused it (document, ingestion record, admin).
            $table->nullableMorphs('subject');
            $table->foreignId('caused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();

            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'metric']);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
