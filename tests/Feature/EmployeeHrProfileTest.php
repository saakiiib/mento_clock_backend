<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeHrProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_admin_can_create_full_employee_profile_and_sensitive_numbers_are_encrypted(): void
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'HR Profile Client', 'timezone' => 'Europe/London', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('modules')->where('available', true)->get(['id']) as $module) {
            DB::table('business_modules')->insert([
                'business_id' => $businessId, 'module_id' => $module->id, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $branchId = DB::table('branches')->insertGetId([
            'business_id' => $businessId, 'name' => 'Milton Keynes', 'address' => 'Test address',
            'latitude' => 52.04, 'longitude' => -0.76, 'radius_m' => 100, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = User::create([
            'business_id' => $businessId, 'name' => 'HR Admin', 'email' => 'hr-admin@example.test',
            'password' => 'secure-admin-password', 'role' => 'admin', 'active' => true,
        ]);

        $this->actingAs($admin, 'web')->post('/employees', [
            'first_name' => 'Sarah', 'middle_name' => 'Anne', 'last_name' => 'Johnson',
            'email' => 'sarah.johnson@example.test', 'password' => 'employee-password-123',
            'phone' => '+447700900001', 'employee_code' => 'EMP001', 'gender' => 'female',
            'national_insurance_number' => 'AB123456C', 'date_of_birth' => '1996-04-06',
            'home_address_line_1' => '1 Sample Road', 'home_postcode' => 'MK1 1AA',
            'bank_name' => 'Sample Bank', 'sort_code' => '12-34-56', 'bank_account_number' => '12345678',
            'emergency_contact_name' => 'Alex Johnson', 'emergency_contact_relationship' => 'Partner',
            'emergency_contact_phone' => '+447700900002', 'emergency_contact_email' => 'alex@example.test',
            'passport_number' => 'P12345678', 'visa_brp_number' => 'BRP123456', 'cos_number' => 'COS123456',
            'department' => 'Human Resources', 'job_title' => 'HR Manager', 'employment_type' => 'full_time',
            'employment_start_date' => '2024-10-06', 'branches' => [$branchId],
        ])->assertRedirect('/people')->assertSessionHas('status');

        $employee = User::where('email', 'sarah.johnson@example.test')->firstOrFail();
        $this->assertSame('Sarah Anne Johnson', $employee->name);
        $this->assertSame('Sarah', $employee->first_name);
        $this->assertSame('HR Manager', $employee->job_title);
        $this->assertSame('AB123456C', $employee->national_insurance_number);
        $this->assertSame('12345678', $employee->bank_account_number);
        $this->assertSame('P12345678', $employee->passport_number);
        $this->assertSame('BRP123456', $employee->visa_brp_number);
        $this->assertSame('COS123456', $employee->cos_number);
        $this->assertNotSame('AB123456C', DB::table('users')->where('id', $employee->id)->value('national_insurance_number'));
        $this->assertNotSame('12345678', DB::table('users')->where('id', $employee->id)->value('bank_account_number'));
        $this->assertArrayNotHasKey('national_insurance_number', $employee->toArray());
        $this->assertDatabaseHas('employee_branches', [
            'business_id' => $businessId, 'user_id' => $employee->id, 'branch_id' => $branchId,
        ]);
    }
}
