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
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED PK AUTO_INCREMENT
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->enum('backend', ['walrus', 'other']);
            $table->text('blob_ref')->nullable(); // tham chiếu bản backup
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])
                ->default('pending');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
