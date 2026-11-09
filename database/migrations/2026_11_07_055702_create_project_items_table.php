<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_items', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Relationships
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('project_items')
                ->nullOnDelete();

            // File / Folder information
            $table->enum('kind', ['file', 'folder']);

            $table->string('name');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->char('checksum_sha256', 64)->nullable();

            // Storage information
            $table->string('storage_backend', 100)->nullable();

            $table->text('storage_ref')->nullable();

            // Encryption information
            $table->string('encryption_key_ref', 255)->nullable();

            $table->string('encryption_key_version', 100)->nullable();

            // Antivirus scanning
            $table->enum('scan_status', [
                'pending',
                'clean',
                'quarantined',
                'failed'
            ])->nullable();

            $table->timestamp('scanned_at')->nullable();

            // User tracking
            $table->foreignId('created_by')
                ->constrained('users');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Timestamps and soft delete
            $table->timestamps();

            $table->softDeletes();

            // Indexes
            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'kind']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_items');
    }
};
