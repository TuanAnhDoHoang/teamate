<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resource_grants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->foreignId('resource_type_id')
                ->constrained('resource_types');

            $table->unsignedBigInteger('resource_specific');

            $table->unsignedTinyInteger('permission_mask')
                ->comment('Bitmask: 1=own, 2=write, 4=read; valid range 1–7');

            $table->foreignId('granted_by')
                ->constrained('users');

            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['role_id', 'resource_type_id', 'resource_specific'],
                'resource_grants_scope_unique'
            );
        });

        DB::statement('
    ALTER TABLE resource_grants
    ADD CONSTRAINT chk_resource_grants_permission_mask
    CHECK (permission_mask BETWEEN 1 AND 7)
');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_grants');
    }
};
