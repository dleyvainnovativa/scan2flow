<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // QA status. Existing documents default to 'approved' so the feature
            // doesn't retroactively hide everything already in the system;
            // NEW documents default to 'pending' (set in the app layer).
            $table->string('status', 12)->default('pending')->after('ocr_status');
            $table->foreignId('uploaded_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('uploaded_by');
            $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();

            $table->index('status');
        });

        // Backfill: everything that already exists is treated as approved, so
        // current users don't suddenly lose visibility on migrate.
        DB::table('documents')->update(['status' => 'approved']);

        Schema::table('area_user', function (Blueprint $table) {
            $table->boolean('can_approve')->default(false)->after('can_edit');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'rejection_reason', 'reviewed_at']);
        });

        Schema::table('area_user', function (Blueprint $table) {
            $table->dropColumn('can_approve');
        });
    }
};
