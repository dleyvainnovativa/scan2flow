<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // Extracted text (OCR from scans, or the PDF's own text layer).
            $table->longText('body')->nullable();
            // Source of the text: 'ocr' (Module 1), 'pdf_text' (digital layer), 'manual'.
            $table->string('source', 20)->default('ocr');
            $table->timestamps();

            $table->unique('document_id');
        });

        // FULLTEXT index for MySQL search. Requires InnoDB (default on MySQL 5.6+/8).
        DB::statement('ALTER TABLE document_contents ADD FULLTEXT fulltext_body (body)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_contents');
    }
};
