<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $key): array
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Leave '.$key, 'timezone' => 'Europe/London', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('modules')->whereIn('code', ['employee_management', 'attendance_tracking', 'leave_management', 'holiday_calendar'])->get(['id']) as $module) {
            DB::table('business_modules')->insert([
                'business_id' => $businessId, 'module_id' => $module->id, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $admin = User::create([
            'business_id' => $businessId, 'name' => 'Manager '.$key, 'email' => $key.'-manager@example.test',
            'password' => 'secure-manager-password', 'role' => 'admin', 'active' => true,
        ]);
        $employee = User::create([
            'business_id' => $businessId, 'name' => 'Employee '.$key, 'email' => $key.'-employee@example.test',
            'password' => 'secure-employee-password', 'role' => 'employee', 'active' => true,
        ]);
        DB::table('business_leave_policies')->insert([
            'business_id' => $businessId, 'leave_year_start_month' => 1, 'leave_year_start_day' => 1,
            'default_annual_entitlement' => 8, 'holiday_region' => 'england_wales',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$businessId, $admin, $employee];
    }

    public function test_employee_request_counts_workdays_excluding_configured_holidays_and_admin_approval_is_audited(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('alpha');
        DB::table('business_holidays')->insert([
            'business_id' => $businessId, 'name' => 'Workspace holiday', 'holiday_date' => '2027-03-02',
            'region' => 'england_wales', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/leave/requests', [
            'leave_type' => 'annual', 'start_date' => '2027-03-01', 'end_date' => '2027-03-03',
            'reason' => 'Family event',
        ])->assertCreated();

        $leaveRequest = DB::table('leave_requests')->where('business_id', $businessId)->firstOrFail();
        $this->assertSame('2.00', number_format((float) $leaveRequest->working_days, 2, '.', ''));
        $this->assertSame('pending', $leaveRequest->status);

        $this->actingAs($admin, 'web')->post('/leave/requests/'.$leaveRequest->id.'/review', ['decision' => 'approved'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('leave_requests', ['id' => $leaveRequest->id, 'business_id' => $businessId, 'status' => 'approved']);
        $this->assertDatabaseHas('leave_request_events', [
            'business_id' => $businessId, 'leave_request_id' => $leaveRequest->id,
            'action' => 'approved', 'from_status' => 'pending', 'to_status' => 'approved',
        ]);
        $this->actingAs($employee, 'sanctum')->getJson('/api/leave')->assertOk()
            ->assertJsonPath('balance.used', 2)->assertJsonPath('balance.remaining', 6);
    }

    public function test_admin_can_book_approved_leave_for_an_employee_and_the_booking_is_audited(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('admin-booking');

        $this->actingAs($admin, 'web')->post('/leave/requests/book', [
            'employee_id' => $employee->id, 'leave_type' => 'annual',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-02',
            'reason' => 'Agreed with employee', 'confirm_booking' => '1',
        ])->assertRedirect()->assertSessionHas('status', 'Leave booked and approved for '.$employee->name.'.');

        $leaveRequest = DB::table('leave_requests')->where('business_id', $businessId)->firstOrFail();
        $this->assertSame('approved', $leaveRequest->status);
        $this->assertSame((int) $admin->id, (int) $leaveRequest->reviewed_by);
        $this->assertSame('2.00', number_format((float) $leaveRequest->working_days, 2, '.', ''));
        $this->assertDatabaseHas('leave_request_events', [
            'business_id' => $businessId, 'leave_request_id' => $leaveRequest->id,
            'actor_id' => $admin->id, 'action' => 'admin_booked', 'from_status' => null, 'to_status' => 'approved',
        ]);
        $this->assertDatabaseHas('leave_admin_events', [
            'business_id' => $businessId, 'actor_id' => $admin->id,
            'subject_type' => 'leave_request', 'subject_id' => $leaveRequest->id, 'action' => 'employee_leave_booked',
        ]);
        $this->actingAs($employee, 'sanctum')->getJson('/api/leave')->assertOk()
            ->assertJsonPath('balance.used', 2)->assertJsonPath('balance.remaining', 6)
            ->assertJsonPath('requests.0.status', 'approved');
    }

    public function test_admin_booking_requires_confirmation_and_respects_rota_conflicts(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('admin-booking-conflict');
        DB::table('rota_shifts')->insert([
            'business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Day shift',
            'starts_at' => '2027-03-01 09:00:00', 'ends_at' => '2027-03-01 17:00:00',
            'break_minutes' => 30, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $booking = [
            'employee_id' => $employee->id, 'leave_type' => 'annual',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-01',
        ];

        $this->actingAs($admin, 'web')->post('/leave/requests/book', $booking)->assertSessionHasErrors('confirm_booking');
        $this->actingAs($admin, 'web')->post('/leave/requests/book', [...$booking, 'confirm_booking' => '1'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_employee_cannot_view_or_cancel_another_business_leave_request(): void
    {
        [$businessA, $adminA, $employeeA] = $this->workspace('alpha');
        [$businessB, $adminB, $employeeB] = $this->workspace('beta');
        $requestId = DB::table('leave_requests')->insertGetId([
            'business_id' => $businessB, 'user_id' => $employeeB->id, 'leave_type' => 'sick',
            'start_date' => '2027-03-01', 'end_date' => '2027-03-01', 'working_days' => 1,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employeeA, 'sanctum')->postJson('/api/leave/requests/'.$requestId.'/cancel')->assertNotFound();
        $this->actingAs($adminA, 'web')->post('/leave/requests/'.$requestId.'/review', ['decision' => 'approved'])->assertNotFound();
        $this->assertDatabaseHas('leave_requests', ['id' => $requestId, 'business_id' => $businessB, 'status' => 'pending']);
    }

    public function test_annual_leave_is_blocked_until_the_employee_has_a_configured_entitlement(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('gamma');
        DB::table('business_leave_policies')->where('business_id', $businessId)->update(['default_annual_entitlement' => 0]);

        $this->actingAs($employee, 'sanctum')->postJson('/api/leave/requests', [
            'leave_type' => 'annual', 'start_date' => '2027-03-01', 'end_date' => '2027-03-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('leave_type');
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_leave_api_is_blocked_when_the_business_does_not_have_the_module_entitlement(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('delta');
        $moduleId = DB::table('modules')->where('code', 'leave_management')->value('id');
        DB::table('business_modules')->where('business_id', $businessId)->where('module_id', $moduleId)->delete();

        $this->actingAs($employee, 'sanctum')->getJson('/api/leave')->assertForbidden();
    }
}
