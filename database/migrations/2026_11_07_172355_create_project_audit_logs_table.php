<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED PK AUTO_INCREMENT
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users');
            $table->string('action', 100);
            $table->string('resource_type', 100);
            $table->string('resource_id', 255); // lưu dạng chuỗi để hỗ trợ nhiều kiểu ID
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_audit_logs');
    }
};
