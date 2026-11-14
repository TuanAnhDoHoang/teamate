<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_item_versions', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED PK AUTO_INCREMENT
            $table->foreignId('project_item_id')
                ->constrained('project_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_backend', 100);
            $table->text('storage_ref');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('encryption_key_ref', 255)->nullable();
            $table->string('encryption_key_version', 100)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['project_item_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_item_versions');
    }
};
