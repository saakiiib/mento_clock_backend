<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmployeeLifecycle;
use App\Services\HrReminderService;
use App\Services\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkforceOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $name): array
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => $name, 'timezone' => 'Europe/London', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('modules')->whereIn('code', ['employee_management', 'attendance_tracking', 'reports_analytics', 'rota_shifts', 'employee_lifecycle', 'hr_reminders', 'hr_reports', 'role_permissions'])->get(['id']) as $module) {
            DB::table('business_modules')->insert(['business_id' => $businessId, 'module_id' => $module->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = User::create(['business_id' => $businessId, 'name' => 'Admin '.$name, 'email' => $name.'-admin@example.test', 'password' => 'test-password-123', 'role' => 'admin', 'active' => true]);
        $employee = User::create(['business_id' => $businessId, 'name' => 'Employee '.$name, 'email' => $name.'-employee@example.test', 'password' => 'test-password-123', 'role' => 'employee', 'active' => true]);
        return [$businessId, $admin, $employee];
    }

    public function test_onboarding_opens_a_tenant_scoped_checklist(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('workflow');
        $this->actingAs($admin)->post('/lifecycle', [
            'user_id' => $employee->id, 'type' => 'onboarding', 'effective_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('status');

        $case = DB::table('employee_lifecycle_cases')->where('business_id', $businessId)->first();
        $this->assertSame('onboarding', $case->type);
        $this->assertSame(8, DB::table('employee_lifecycle_tasks')->where('business_id', $businessId)->where('case_id', $case->id)->count());
        $this->actingAs($admin)->get('/lifecycle')->assertOk()->assertSee('right-to-work evidence review');
    }

    public function test_offboarding_revokes_existing_mobile_tokens_when_workflow_is_completed(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('leaver');
        $employee->createToken('mobile');
        $caseId = app(EmployeeLifecycle::class)->open($businessId, $employee->id, 'offboarding', now()->toDateString(), $admin->id);
        DB::table('employee_lifecycle_tasks')->where('case_id', $caseId)->update(['status' => 'complete']);

        $this->actingAs($admin)->post('/lifecycle/'.$caseId.'/complete')->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('employee_lifecycle_cases', ['id' => $caseId, 'status' => 'complete']);
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'active' => false, 'employment_end_date' => now()->toDateString()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $employee->id]);
    }

    public function test_custom_role_limits_portal_access_without_changing_employee_app_role(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('roles');
        $roleId = DB::table('business_roles')->insertGetId([
            'business_id' => $businessId, 'code' => 'reports_only', 'name' => 'Reports only',
            'permissions' => json_encode(['workspace.access', 'reports.view']), 'is_system' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $employee->id)->update(['business_role_id' => $roleId]);
        $employee->refresh();

        $this->assertTrue(app(WorkspacePermissions::class)->has($employee, 'workspace.access'));
        $this->assertTrue(app(WorkspacePermissions::class)->has($employee, 'reports.view'));
        $this->assertFalse(app(WorkspacePermissions::class)->has($employee, 'people.sensitive.view'));
        $this->assertSame('employee', $employee->role);
        $this->actingAs($employee)->get('/reports')->assertOk();
        $this->get('/')->assertOk()->assertDontSee('Recent attendance');
        $this->get('/employees/'.$employee->id.'/edit')->assertForbidden();
    }

    public function test_rota_blocks_a_shift_during_recorded_unavailability(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('availability');
        DB::table('rota_availability')->insert([
            'business_id' => $businessId, 'user_id' => $employee->id, 'starts_at' => '2027-04-05 09:00:00',
            'ends_at' => '2027-04-05 13:00:00', 'availability' => 'unavailable', 'created_by' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->post('/rota/shifts', [
            'user_id' => $employee->id, 'title' => 'Morning', 'starts_at' => '2027-04-05T11:00',
            'ends_at' => '2027-04-05T17:00', 'break_minutes' => 0,
        ])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('rota_shifts', 0);
    }

    public function test_draft_shift_can_be_edited_and_the_change_is_audited(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('rota-edit');
        $shiftId = DB::table('rota_shifts')->insertGetId([
            'business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Morning',
            'starts_at' => '2027-04-05 09:00:00', 'ends_at' => '2027-04-05 13:00:00',
            'break_minutes' => 0, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post('/rota/shifts/'.$shiftId, [
            'user_id' => $employee->id, 'title' => 'Extended morning',
            'starts_at' => '2027-04-05T08:00', 'ends_at' => '2027-04-05T14:00',
            'break_minutes' => 30, 'notes' => 'Updated coverage',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('rota_shifts', ['id' => $shiftId, 'title' => 'Extended morning', 'break_minutes' => 30]);
        $this->assertDatabaseHas('rota_events', ['rota_shift_id' => $shiftId, 'action' => 'draft_shift_updated']);
    }

    public function test_reminder_job_creates_an_expiry_alert_once(): void
    {
        Mail::fake();
        [$businessId, $admin, $employee] = $this->workspace('reminder');
        $today = now('Europe/London')->toDateString();
        DB::table('employee_documents')->insert([
            'business_id' => $businessId, 'user_id' => $employee->id, 'category' => 'right_to_work',
            'title' => 'Right to work', 'original_name' => 'check.pdf', 'storage_path' => 'private/check.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 100, 'expiry_date' => $today,
            'status' => 'accepted', 'uploaded_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = app(HrReminderService::class)->run();
        $second = app(HrReminderService::class)->run();
        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseHas('hr_reminders', ['business_id' => $businessId, 'reminder_type' => 'document_expiry', 'subject_type' => 'employee_document']);
    }
}
