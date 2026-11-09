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
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('scope_type')
                ->constrained('teams');
        });

        $teamScopedRoles = DB::table('memberships')
            ->join('roles', 'roles.id', '=', 'memberships.role_id')
            ->join('teams', 'teams.id', '=', 'memberships.scope_id')
            ->where('roles.scope_type', 'team')
            ->where('roles.is_system', false)
            ->groupBy('roles.id')
            ->select('roles.id')
            ->selectRaw('COUNT(DISTINCT memberships.scope_id) as team_count')
            ->selectRaw('MIN(memberships.scope_id) as team_id')
            ->havingRaw('COUNT(DISTINCT memberships.scope_id) = 1')
            ->get();

        foreach ($teamScopedRoles as $teamScopedRole) {
            DB::table('roles')
                ->where('id', $teamScopedRole->id)
                ->update(['team_id' => $teamScopedRole->team_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
