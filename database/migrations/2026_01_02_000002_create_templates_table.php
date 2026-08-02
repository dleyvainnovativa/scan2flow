<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // e.g. "Facturas CxC"
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('input_folder_path')->nullable();   // OUTPUT of the capture module (Module 1)
            $table->string('naming_rule')->default('same_name'); // how PDF+XML are paired
            $table->timestamps();

            $table->unique(['area_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
