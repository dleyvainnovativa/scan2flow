<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_field_id')->constrained()->cascadeOnDelete();

            // Raw display value as captured.
            $table->string('value')->nullable();
            // Normalized value for filtering/sorting across types:
            //   date -> YYYY-MM-DD, currency/number -> zero-padded/decimal string, text -> lowercased.
            $table->string('value_norm')->nullable();

            $table->timestamps();

            $table->unique(['document_id', 'template_field_id']);
            // Composite index powers "filter by field key + value" queries.
            $table->index(['template_field_id', 'value_norm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_metadata');
    }
};
