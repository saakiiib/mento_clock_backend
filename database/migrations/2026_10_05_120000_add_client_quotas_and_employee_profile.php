<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('branch_limit')->default(1);
            $table->unsignedSmallInteger('employees_per_branch_limit')->default(10);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedSmallInteger('employee_limit')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 100)->nullable();
            $table->date('employment_start_date')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
        });

        // Preserve existing customers' current active branch count when introducing quotas.
        foreach (DB::table('businesses')->select('id')->get() as $business) {
            $activeBranches = DB::table('branches')->where('business_id', $business->id)->where('active', true)->count();
            $employeeLimit = 10;
            foreach (DB::table('branches')->where('business_id', $business->id)->where('active', true)->pluck('id') as $branchId) {
                $assigned = DB::table('employee_branches as eb')->join('users as u', 'u.id', '=', 'eb.user_id')
                    ->where('eb.business_id', $business->id)->where('eb.branch_id', $branchId)
                    ->where('u.role', 'employee')->where('u.active', true)->count();
                $employeeLimit = max($employeeLimit, $assigned);
            }
            $values = ['branch_limit' => max(1, min(500, $activeBranches)), 'employees_per_branch_limit' => min(500, $employeeLimit), 'updated_at' => now('UTC')];
            if (DB::table('client_profiles')->where('business_id', $business->id)->exists()) {
                DB::table('client_profiles')->where('business_id', $business->id)->update($values);
            } else {
                DB::table('client_profiles')->insert(['business_id' => $business->id, ...$values, 'created_at' => now('UTC')]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'employment_start_date', 'emergency_contact_name', 'emergency_contact_phone']);
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('employee_limit');
        });
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn(['branch_limit', 'employees_per_branch_limit']);
        });
    }
};
