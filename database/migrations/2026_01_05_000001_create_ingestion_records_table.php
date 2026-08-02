<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();

            $table->string('base_name');                  // shared PDF/XML base filename
            $table->string('source_pdf_path')->nullable(); // original path in INPUT
            $table->string('source_xml_path')->nullable();

            $table->enum('status', ['pending', 'processing', 'done', 'failed', 'skipped'])
                  ->default('pending');
            $table->string('error')->nullable();
            $table->json('meta')->nullable();             // parsed values snapshot / debug

            $table->timestamps();

            // A given base name per template is ingested once (idempotency).
            $table->unique(['template_id', 'base_name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_records');
    }
};
