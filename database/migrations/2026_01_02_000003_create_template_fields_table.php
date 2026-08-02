<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('key');            // slug used by filters + Module 1 mapping (e.g. "folio")
            $table->string('label');          // human label (e.g. "Folio")
            $table->enum('type', ['text', 'number', 'date', 'currency', 'select']);
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('position')->default(0);
            $table->json('options')->nullable();   // for 'select': array of allowed values
            $table->timestamps();

            $table->unique(['template_id', 'key']);
            $table->index(['template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fields');
    }
};
