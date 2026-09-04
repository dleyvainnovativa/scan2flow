<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sftp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name');                       // "SFTP de Cliente X"
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);
            $table->string('username');

            $table->string('auth_type', 12)->default('password'); // password | key

            // Encrypted at rest (Laravel 'encrypted' cast). Nullable so only the
            // relevant auth fields are populated.
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->text('passphrase')->nullable();

            // Root on the remote server; template input_folder_path is relative
            // to this. processed_path is where ingested files are moved.
            $table->string('base_path')->default('/');
            $table->string('processed_path')->default('processed');

            $table->timestamps();

            $table->index(['tenant_id']);
        });

        // Template input source.
        Schema::table('templates', function (Blueprint $table) {
            $table->string('input_driver', 12)->default('local')->after('input_folder_path'); // local | sftp
            $table->foreignId('sftp_connection_id')->nullable()->after('input_driver')
                ->constrained('sftp_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sftp_connection_id');
            $table->dropColumn('input_driver');
        });
        Schema::dropIfExists('sftp_connections');
    }
};
