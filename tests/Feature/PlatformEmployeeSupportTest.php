<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Notification};
use Tests\TestCase;

class PlatformEmployeeSupportTest extends TestCase
{
    use RefreshDatabase;

    private function supportAdmin(): void
    {
        $admin = PlatformAdmin::create(['name' => 'Support Admin', 'email' => 'support@example.test', 'password' => 'platform-password-123', 'active' => true]);
        $this->actingAs($admin, 'platform')->withSession(['platform_version' => $admin->auth_version]);
        Auth::shouldUse('web');
    }

    private function employee(): array
    {
        $businessId = DB::table('businesses')->insertGetId(['name' => 'Riverside Cafe', 'timezone' => 'Europe/London', 'active' => true]);
        $branchId = DB::table('branches')->insertGetId([
            'business_id' => $businessId, 'name' => 'Riverside', 'address' => '', 'latitude' => null, 'longitude' => null,
            'radius_m' => 100, 'active' => true, 'location_required' => false,
        ]);
        $employee = User::create([
            'business_id' => $businessId, 'name' => 'Alex Employee', 'email' => 'alex@example.test',
            'password' => 'employee-password-123', 'role' => 'employee', 'active' => true,
        ]);
        DB::table('employee_branches')->insert(['business_id' => $businessId, 'user_id' => $employee->id, 'branch_id' => $branchId]);
        return [$employee, $businessId, $branchId];
    }

    public function test_employee_support_is_super_admin_only_and_scoped_to_employee_accounts(): void
    {
        [$employee] = $this->employee();
        $this->get('/platform/support')->assertRedirect('/login');

        $this->supportAdmin();
        $this->get('/platform/support?q=Alex')->assertOk()->assertSee('Alex Employee')->assertSee('Riverside Cafe');
        $this->get('/platform/support/employees/'.$employee->id)->assertOk()->assertSee('Help restore app access')->assertSee('Recent clock records');
    }

    public function test_support_can_send_a_reset_link_without_setting_or_viewing_password(): void
    {
        [$employee] = $this->employee();
        $this->supportAdmin();
        Notification::fake();

        $this->post('/platform/support/employees/'.$employee->id.'/reset-link', [
            'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        Notification::assertSentTo($employee, ResetPassword::class);
        $this->assertDatabaseHas('platform_audit_logs', [
            'business_id' => $employee->business_id, 'action' => 'Employee password reset link sent',
        ]);
    }

    public function test_support_can_correct_login_email_with_client_authorisation(): void
    {
        [$employee, $businessId] = $this->employee();
        $this->supportAdmin();

        $this->post('/platform/support/employees/'.$employee->id.'/email', [
            'email' => 'alex.new@example.test', 'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'email' => 'alex.new@example.test']);
        $this->assertDatabaseHas('platform_audit_logs', [
            'business_id' => $businessId, 'action' => 'Employee login email updated by support',
        ]);
    }

    public function test_support_can_add_a_client_authorised_missed_clock_record(): void
    {
        [$employee, $businessId, $branchId] = $this->employee();
        $this->supportAdmin();

        $this->post('/platform/support/employees/'.$employee->id.'/attendance', [
            'branch_id' => $branchId, 'clock_in' => '2025-11-15T14:23', 'clock_out' => '2025-11-15T22:09',
            'reason' => 'Client confirmed the shift by email.', 'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $record = DB::table('attendance_records')->where('user_id', $employee->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('2025-11-15 14:23:00', $record->clock_in);
        $this->assertSame('2025-11-15 22:09:00', $record->clock_out);
        $this->assertNull($record->in_lat);
        $this->assertDatabaseHas('platform_audit_logs', [
            'business_id' => $businessId, 'action' => 'Missed attendance added by platform support',
        ]);
    }

    public function test_support_cannot_add_a_missed_clock_record_without_client_authorisation(): void
    {
        [$employee, , $branchId] = $this->employee();
        $this->supportAdmin();

        $this->post('/platform/support/employees/'.$employee->id.'/attendance', [
            'branch_id' => $branchId, 'clock_in' => '2025-11-15T14:23', 'clock_out' => '2025-11-15T22:09',
            'reason' => 'Client confirmed the shift by email.', 'client_contact' => 'Raju, business owner',
        ])->assertSessionHasErrors(['client_confirmed']);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_support_correction_clears_finalisation_and_logs_client_authorisation(): void
    {
        [$employee, $businessId, $branchId] = $this->employee();
        $attendanceId = DB::table('attendance_records')->insertGetId([
            'business_id' => $businessId, 'user_id' => $employee->id, 'branch_id' => $branchId,
            'clock_in' => '2025-11-15 13:00:00', 'clock_out' => '2025-11-15 17:00:00',
            'in_lat' => null, 'in_lng' => null, 'out_lat' => null, 'out_lng' => null,
            'finalized_at' => '2025-11-16 10:00:00', 'finalized_by' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->supportAdmin();

        $this->post('/platform/support/attendance/'.$attendanceId, [
            'clock_in' => '2025-11-15T14:23', 'clock_out' => '2025-11-15T22:09',
            'reason' => 'Client confirmed the shift by email.', 'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $record = DB::table('attendance_records')->find($attendanceId);
        $this->assertSame('2025-11-15 14:23:00', $record->clock_in);
        $this->assertNull($record->finalized_at);
        $this->assertDatabaseHas('platform_audit_logs', [
            'business_id' => $businessId, 'action' => 'Attendance corrected by platform support',
        ]);
    }
}
