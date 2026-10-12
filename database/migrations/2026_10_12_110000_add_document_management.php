<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['employee_documents', 'client_organisation_documents'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $needsArchivedAt = ! Schema::hasColumn($tableName, 'archived_at');
            $needsArchivedBy = ! Schema::hasColumn($tableName, 'archived_by');
            if ($needsArchivedAt || $needsArchivedBy) {
                Schema::table($tableName, function (Blueprint $table) use ($needsArchivedAt, $needsArchivedBy) {
                    if ($needsArchivedAt) {
                        $table->timestamp('archived_at')->nullable();
                    }
                    if ($needsArchivedBy) {
                        $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                });
            }
        }

        $now = now('UTC');
        DB::table('modules')->updateOrInsert(
            ['code' => 'document_management'],
            ['name' => 'Documents', 'description' => 'Employee and organisation document records, reports and archives.',
                'is_core' => false, 'available' => true, 'sort_order' => 95, 'updated_at' => $now, 'created_at' => $now]
        );

        if (Schema::hasTable('business_roles')) {
            $roleGrants = [
                'hr_manager' => ['documents.view', 'documents.manage', 'documents.download'],
                'report_viewer' => ['documents.view'],
            ];
            foreach (DB::table('business_roles')->where('is_system', true)->whereIn('code', array_keys($roleGrants))->get(['id', 'permissions', 'code']) as $role) {
                $permissions = is_array($role->permissions) ? $role->permissions : (json_decode($role->permissions ?? '[]', true) ?: []);
                DB::table('business_roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique([...$permissions, ...$roleGrants[$role->code]])), JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve archived documents and module assignments during rollback.
    }
};
