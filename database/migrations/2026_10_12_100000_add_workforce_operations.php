<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_roles')) {
            Schema::create('business_roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name', 100);
                $table->json('permissions');
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['business_id', 'code']);
            });
        }
        if (!Schema::hasColumn('users', 'business_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('business_role_id')->nullable()->after('role')->constrained('business_roles')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('users', 'employment_end_date')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('employment_end_date')->nullable()->after('employment_start_date');
            });
        }

        if (!Schema::hasTable('employee_lifecycle_cases')) {
            Schema::create('employee_lifecycle_cases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('type', 20);
                $table->string('status', 20)->default('in_progress');
                $table->date('effective_date')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'type', 'status']);
                $table->index(['business_id', 'user_id', 'effective_date'], 'employee_lifecycle_cases_business_user_effective_date_index');
            });
        }
        if (!Schema::hasTable('employee_lifecycle_tasks')) {
            Schema::create('employee_lifecycle_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('case_id')->constrained('employee_lifecycle_cases')->cascadeOnDelete();
                $table->string('title', 180);
                $table->string('category', 60);
                $table->string('status', 20)->default('pending');
                $table->date('due_date')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'status', 'due_date']);
            });
        }

        if (!Schema::hasTable('rota_availability')) {
            Schema::create('rota_availability', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('availability', 20)->default('unavailable');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['business_id', 'user_id', 'starts_at', 'ends_at']);
            });
        }
        if (!Schema::hasTable('rota_templates')) {
            Schema::create('rota_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->string('name', 120);
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->json('shifts');
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['business_id', 'active']);
            });
        }
        if (!Schema::hasTable('hr_reminders')) {
            Schema::create('hr_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->string('reminder_type', 60);
                $table->string('subject_type', 60);
                $table->unsignedBigInteger('subject_id');
                $table->string('title', 180);
                $table->text('body');
                $table->date('remind_on');
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'reminder_type', 'subject_type', 'subject_id', 'remind_on'], 'hr_reminders_dedupe_idx');
                $table->index(['business_id', 'read_at', 'remind_on']);
            });
        }

        // New modules appear in the Super Admin catalogue but are not assigned
        // automatically to existing client workspaces.
        $modules = [
            ['code' => 'employee_lifecycle', 'name' => 'Onboarding & Offboarding', 'description' => 'Employee joining and leaving workflows with assigned checklists.', 'sort_order' => 90],
            ['code' => 'rota_advanced', 'name' => 'Rota Planning Tools', 'description' => 'Availability, reusable shift templates and improved rota coverage.', 'sort_order' => 100],
            ['code' => 'hr_reminders', 'name' => 'HR Reminders', 'description' => 'Scheduled reminders for expiring records, contracts and outstanding actions.', 'sort_order' => 110],
            ['code' => 'hr_reports', 'name' => 'HR Reports', 'description' => 'Workforce, leave, rota, training and compliance reporting.', 'sort_order' => 120],
            ['code' => 'role_permissions', 'name' => 'Roles & Permissions', 'description' => 'Client-managed roles with granular workspace permissions.', 'sort_order' => 130],
        ];
        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(['code' => $module['code']], [
                'name' => $module['name'], 'description' => $module['description'],
                'is_core' => false, 'available' => true, 'sort_order' => $module['sort_order'],
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        }

        $permissionSets = [
            'hr_manager' => ['workspace.access', 'people.view', 'people.manage', 'attendance.view', 'attendance.correct', 'leave.view', 'leave.manage', 'rota.view', 'rota.manage', 'compliance.view', 'compliance.documents.download', 'compliance.manage', 'notices.view', 'notices.manage', 'reports.view', 'reports.export', 'organisation.view', 'organisation.manage', 'lifecycle.view', 'lifecycle.manage', 'reminders.view', 'roles.manage'],
            'rota_manager' => ['workspace.access', 'people.view', 'rota.view', 'rota.manage', 'leave.view', 'reports.view'],
            'supervisor' => ['workspace.access', 'people.view', 'attendance.view', 'leave.view', 'rota.view', 'notices.view', 'reports.view'],
            'report_viewer' => ['workspace.access', 'people.view', 'attendance.view', 'leave.view', 'rota.view', 'compliance.view', 'notices.view', 'reports.view', 'organisation.view', 'lifecycle.view', 'reminders.view'],
        ];
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            foreach ($permissionSets as $code => $permissions) {
                DB::table('business_roles')->updateOrInsert(
                    ['business_id' => $businessId, 'code' => $code],
                    ['name' => ucwords(str_replace('_', ' ', $code)), 'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'is_system' => true, 'updated_at' => now('UTC'), 'created_at' => now('UTC')]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep live workflow, permission and reminder records on rollback.
    }
};
