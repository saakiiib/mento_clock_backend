<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash, Notification};
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

    public function test_super_admin_can_transfer_employee_between_businesses_in_same_client_and_keep_history(): void
    {
        [$employee, $sourceBusinessId, $sourceBranchId] = $this->employee();
        $accountId = DB::table('client_accounts')->insertGetId([
            'name' => 'Sushi Group', 'contact_name' => 'Owner', 'contact_email' => 'owner@example.test',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('businesses')->where('id', $sourceBusinessId)->update(['client_account_id' => $accountId]);
        $destinationBusinessId = DB::table('businesses')->insertGetId([
            'client_account_id' => $accountId, 'name' => 'Fw Sushi Bar Ltd', 'timezone' => 'Europe/London',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $destinationBranchId = DB::table('branches')->insertGetId([
            'business_id' => $destinationBusinessId, 'name' => 'Fw Central', 'address' => '', 'latitude' => null,
            'longitude' => null, 'radius_m' => 100, 'active' => true, 'location_required' => false,
        ]);
        $attendanceId = DB::table('attendance_records')->insertGetId([
            'business_id' => $sourceBusinessId, 'user_id' => $employee->id, 'branch_id' => $sourceBranchId,
            'clock_in' => '2026-11-10 09:00:00', 'clock_out' => '2026-11-10 17:00:00',
            'in_lat' => null, 'in_lng' => null, 'out_lat' => null, 'out_lng' => null,
            'finalized_at' => null, 'finalized_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->supportAdmin();

        // Legacy employee rows may not have the new client_account_id column
        // populated even though the owning business is correctly linked.
        $this->get('/platform/support/employees/'.$employee->id)
            ->assertOk()->assertSee('Fw Sushi Bar Ltd');

        $this->post('/platform/support/employees/'.$employee->id.'/transfer', [
            'destination_business_id' => $destinationBusinessId,
            'branch_ids' => [$destinationBranchId],
            'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'active' => 0, 'business_id' => $sourceBusinessId, 'client_account_id' => $accountId]);
        $this->assertDatabaseHas('users', ['email' => 'alex@example.test', 'business_id' => $destinationBusinessId, 'client_account_id' => $accountId]);
        $destinationEmployee = User::where('email', 'alex@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('employee-password-123', $destinationEmployee->password));
        $this->assertDatabaseHas('employee_branches', ['business_id' => $destinationBusinessId, 'user_id' => $destinationEmployee->id, 'branch_id' => $destinationBranchId]);
        $this->assertDatabaseHas('attendance_records', ['id' => $attendanceId, 'business_id' => $sourceBusinessId, 'user_id' => $employee->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['business_id' => $sourceBusinessId, 'action' => 'Employee transferred to another business']);
        $this->assertDatabaseHas('platform_audit_logs', ['business_id' => $destinationBusinessId, 'action' => 'Employee received from another business']);
    }

    public function test_super_admin_employee_deletion_requires_typed_email_and_removes_attendance(): void
    {
        [$employee, $businessId, $branchId] = $this->employee();
        DB::table('attendance_records')->insert([
            'business_id' => $businessId, 'user_id' => $employee->id, 'branch_id' => $branchId,
            'clock_in' => '2026-11-10 09:00:00', 'clock_out' => '2026-11-10 17:00:00',
            'in_lat' => null, 'in_lng' => null, 'out_lat' => null, 'out_lng' => null,
            'finalized_at' => null, 'finalized_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->supportAdmin();

        $this->post('/platform/support/employees/'.$employee->id.'/delete', [
            'confirm_email' => 'wrong@example.test', 'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $employee->id]);

        $this->post('/platform/support/employees/'.$employee->id.'/delete', [
            'confirm_email' => $employee->email, 'client_contact' => 'Raju, business owner', 'client_confirmed' => '1',
        ])->assertRedirect('/platform/support')->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
        $this->assertDatabaseMissing('attendance_records', ['user_id' => $employee->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['business_id' => $businessId, 'action' => 'Employee and linked records permanently deleted']);
    }
}
