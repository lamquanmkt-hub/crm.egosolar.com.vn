<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_tool_logs')) {
            Schema::create('ai_tool_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
                $table->string('tool', 80);
                $table->string('module', 80)->nullable();
                $table->string('permission', 160)->nullable();
                $table->string('scope', 30)->nullable();
                $table->string('status', 30)->default('success');
                $table->string('period_label', 160)->nullable();
                $table->unsignedInteger('result_count')->default(0);
                $table->string('query_hash', 64)->nullable();
                $table->text('denial_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'created_at']);
                $table->index(['tool', 'status', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_action_drafts')) {
            Schema::create('ai_action_drafts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
                $table->string('action_type', 80);
                $table->string('required_permission', 160)->nullable();
                $table->string('status', 30)->default('draft');
                $table->json('payload');
                $table->json('validation_errors')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'status', 'created_at']);
            });
        }

        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (! Schema::hasTable($permissionsTable) || ! Schema::hasTable($rolesTable) || ! Schema::hasTable($rolePermissionTable)) {
            return;
        }

        $allPermissions = (array) config('ego_ai.all_permissions', []);
        foreach ($allPermissions as $permission) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $permissionIds = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $allPermissions)
            ->pluck('id', 'name');

        foreach ((array) config('ego_ai.role_permission_matrix', []) as $roleName => $permissionNames) {
            $roleId = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            $names = in_array('*', (array) $permissionNames, true) ? $allPermissions : (array) $permissionNames;
            foreach ($names as $name) {
                $permissionId = $permissionIds[$name] ?? null;
                if (! $permissionId) {
                    continue;
                }

                DB::table($rolePermissionTable)->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_drafts');
        Schema::dropIfExists('ai_tool_logs');

        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        if (Schema::hasTable($permissionsTable)) {
            DB::table($permissionsTable)->whereIn('name', (array) config('ego_ai.all_permissions', []))->delete();
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
        }
    }
};
