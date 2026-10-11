<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 500);
            $table->boolean('is_core')->default(false);
            $table->boolean('available')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('business_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('limits')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'module_id']);
            $table->index(['business_id', 'status', 'starts_at', 'ends_at']);
        });

        $now = now('UTC');
        $modules = [
            ['code' => 'employee_management', 'name' => 'Employee Management', 'description' => 'Employee profiles, workplace assignments and employee administration.', 'is_core' => true, 'available' => true, 'sort_order' => 10],
            ['code' => 'attendance_tracking', 'name' => 'Attendance Tracking', 'description' => 'Clock-in/out, attendance history and attendance corrections.', 'is_core' => true, 'available' => true, 'sort_order' => 20],
            ['code' => 'leave_management', 'name' => 'Leave Management', 'description' => 'Leave policies, balances, requests, approvals and team calendars.', 'is_core' => false, 'available' => false, 'sort_order' => 30],
            ['code' => 'rota_shifts', 'name' => 'Rota & Shifts', 'description' => 'Shift planning, rota publication, availability and shift changes.', 'is_core' => false, 'available' => false, 'sort_order' => 40],
            ['code' => 'compliance_tracking', 'name' => 'Compliance Tracking', 'description' => 'Secure compliance records, expiry reminders and evidence history.', 'is_core' => false, 'available' => false, 'sort_order' => 50],
            ['code' => 'reports_analytics', 'name' => 'Dashboard & Reports', 'description' => 'Workforce dashboards, attendance reporting and analytics.', 'is_core' => false, 'available' => true, 'sort_order' => 60],
            ['code' => 'company_notices', 'name' => 'Company Notices', 'description' => 'Company announcements, acknowledgements and staff communications.', 'is_core' => false, 'available' => false, 'sort_order' => 70],
            ['code' => 'holiday_calendar', 'name' => 'Holiday Calendar', 'description' => 'Regional public holidays and company calendar settings.', 'is_core' => false, 'available' => false, 'sort_order' => 80],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->insert([...$module, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Existing MentoClock clients retain the features they already use.
        $existingClients = DB::table('businesses')->pluck('id');
        $availableModules = DB::table('modules')->where('available', true)->get(['id']);
        foreach ($existingClients as $businessId) {
            foreach ($availableModules as $module) {
                DB::table('business_modules')->insert([
                    'business_id' => $businessId,
                    'module_id' => $module->id,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_modules');
        Schema::dropIfExists('modules');
    }
};
