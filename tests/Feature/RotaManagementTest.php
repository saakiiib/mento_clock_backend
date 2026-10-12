<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $key): array
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Rota '.$key, 'timezone' => 'Europe/London', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('modules')->whereIn('code', ['employee_management', 'attendance_tracking', 'leave_management', 'holiday_calendar', 'rota_shifts'])->get(['id']) as $module) {
            DB::table('business_modules')->insert(['business_id' => $businessId, 'module_id' => $module->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = User::create(['business_id' => $businessId, 'name' => 'Manager '.$key, 'email' => $key.'-manager@example.test', 'password' => 'secure-manager-password', 'role' => 'admin', 'active' => true]);
        $employee = User::create(['business_id' => $businessId, 'name' => 'Employee '.$key, 'email' => $key.'-employee@example.test', 'password' => 'secure-employee-password', 'role' => 'employee', 'active' => true]);
        DB::table('business_leave_policies')->insert(['business_id' => $businessId, 'leave_year_start_month' => 1, 'leave_year_start_day' => 1, 'default_annual_entitlement' => 20, 'holiday_region' => 'england_wales', 'created_at' => now(), 'updated_at' => now()]);
        return [$businessId, $admin, $employee];
    }

    public function test_rota_cannot_schedule_an_employee_on_leave_or_a_business_closure(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('alpha');
        DB::table('leave_requests')->insert(['business_id' => $businessId, 'user_id' => $employee->id, 'leave_type' => 'annual', 'start_date' => '2027-03-01', 'end_date' => '2027-03-02', 'working_days' => 2, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin, 'web')->post('/rota/shifts', ['user_id' => $employee->id, 'title' => 'Day shift', 'starts_at' => '2027-03-01T09:00', 'ends_at' => '2027-03-01T17:00', 'break_minutes' => 30])
            ->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('rota_shifts', 0);

        DB::table('leave_requests')->delete();
        DB::table('business_holidays')->insert(['business_id' => $businessId, 'name' => 'Company shutdown', 'holiday_date' => '2027-03-01', 'region' => 'all', 'is_closure' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin, 'web')->post('/rota/shifts', ['user_id' => $employee->id, 'title' => 'Day shift', 'starts_at' => '2027-03-01T09:00', 'ends_at' => '2027-03-01T17:00', 'break_minutes' => 30])
            ->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('rota_shifts', 0);
    }

    public function test_leave_approval_is_blocked_until_an_overlapping_draft_shift_is_cancelled(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('beta');
        $leaveId = DB::table('leave_requests')->insertGetId(['business_id' => $businessId, 'user_id' => $employee->id, 'leave_type' => 'annual', 'start_date' => '2027-03-01', 'end_date' => '2027-03-01', 'working_days' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $shiftId = DB::table('rota_shifts')->insertGetId(['business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Day shift', 'starts_at' => '2027-03-01 09:00:00', 'ends_at' => '2027-03-01 17:00:00', 'break_minutes' => 30, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin, 'web')->post('/leave/requests/'.$leaveId.'/review', ['decision' => 'approved'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('leave_requests', ['id' => $leaveId, 'status' => 'pending']);

        $this->actingAs($admin, 'web')->post('/rota/shifts/'.$shiftId.'/cancel')->assertSessionHas('status');
        $this->actingAs($admin, 'web')->post('/leave/requests/'.$leaveId.'/review', ['decision' => 'approved'])->assertSessionHas('status');
        $this->assertDatabaseHas('leave_requests', ['id' => $leaveId, 'status' => 'approved']);
        $this->assertDatabaseHas('rota_shifts', ['id' => $shiftId, 'status' => 'cancelled']);
    }

    public function test_manager_can_create_multiple_checked_draft_shifts_in_one_submission(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('batch');
        $shift = ['user_id' => $employee->id, 'title' => 'Day shift', 'break_minutes' => 15];
        $this->actingAs($admin, 'web')->post('/rota/shifts/batch', ['shifts' => [
            $shift + ['starts_at' => '2026-10-12T09:00', 'ends_at' => '2026-10-12T17:00'],
            $shift + ['starts_at' => '2026-10-13T09:00', 'ends_at' => '2026-10-13T17:00'],
        ]])->assertRedirect()->assertSessionHas('status', '2 draft shifts saved. Review the week and publish when ready.');

        $this->assertDatabaseCount('rota_shifts', 2);
        $this->assertDatabaseHas('rota_shifts', ['business_id' => $businessId, 'user_id' => $employee->id, 'status' => 'draft']);
        $this->assertDatabaseCount('rota_events', 2);
    }

    public function test_rota_manager_page_uses_an_employee_by_day_grid_and_batch_entry(): void
    {
        [, $admin, $employee] = $this->workspace('manager-grid');
        $this->actingAs($admin, 'web')->get('/rota?week=2026-10-12')->assertOk()
            ->assertSee('Each row is an employee and each column is a day.')
            ->assertSee($employee->name)->assertSee('Mon')->assertSee('Sun')
            ->assertSee('Add shifts to this rota')->assertSee('Add another shift')
            ->assertSee('Templates')->assertSee('Employee availability');
    }

    public function test_batch_rota_creation_rolls_back_all_rows_when_any_shift_conflicts(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('batch-conflict');
        $shift = ['user_id' => $employee->id, 'title' => 'Day shift', 'break_minutes' => 0, 'starts_at' => '2026-10-12T09:00', 'ends_at' => '2026-10-12T17:00'];
        $this->actingAs($admin, 'web')->post('/rota/shifts/batch', ['shifts' => [$shift, $shift]])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('rota_shifts', 0);
        $this->assertDatabaseCount('rota_events', 0);
    }

    public function test_manager_can_publish_all_drafts_for_the_selected_week(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('publish-week');
        foreach (['2026-10-12 09:00:00', '2026-10-13 09:00:00'] as $start) {
            $end = CarbonImmutable::parse($start)->addHours(8)->toDateTimeString();
            DB::table('rota_shifts')->insert([
                'business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Day shift',
                'starts_at' => $start, 'ends_at' => $end, 'break_minutes' => 0,
                'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin, 'web')->post('/rota/publish-week', ['week' => '2026-10-12'])
            ->assertRedirect()->assertSessionHas('status', '2 shifts published. Employees can now see their rota in the app.');

        $this->assertDatabaseCount('rota_shifts', 2);
        $this->assertSame(2, DB::table('rota_shifts')->where('business_id', $businessId)->where('status', 'published')->count());
        $this->assertDatabaseCount('rota_events', 2);
    }

    public function test_weekly_publish_is_all_or_nothing_when_any_draft_conflicts(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('publish-conflict');
        foreach (range(1, 2) as $index) {
            DB::table('rota_shifts')->insert([
                'business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Overlapping shift '.$index,
                'starts_at' => '2026-10-12 09:00:00', 'ends_at' => '2026-10-12 17:00:00',
                'break_minutes' => 0, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin, 'web')->post('/rota/publish-week', ['week' => '2026-10-12'])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(2, DB::table('rota_shifts')->where('business_id', $businessId)->where('status', 'draft')->count());
        $this->assertDatabaseCount('rota_events', 0);
    }

    public function test_weekly_rota_pdf_is_an_employee_day_grid_with_optional_breaks_and_business_header(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('print');
        DB::table('client_profiles')->insert([
            'business_id' => $businessId, 'address' => '10 High Street, London, E1 1AA',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rota_shifts')->insert([
            'business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Day shift',
            'starts_at' => '2026-10-12 08:00:00', 'ends_at' => '2026-10-12 12:00:00',
            'break_minutes' => 15, 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $monday = CarbonImmutable::parse('2026-10-12', 'Europe/London');
        $shift = (object) [
            'local_start' => $monday->setTime(9, 0), 'local_end' => $monday->setTime(13, 0),
            'branch_name' => 'London Shop', 'status' => 'published', 'break_minutes' => 15,
        ];
        $data = [
            'period' => 'week', 'employees' => collect([(object) ['id' => $employee->id, 'name' => $employee->name, 'employee_code' => null]]),
            'shiftGrid' => [$employee->id => ['2026-10-12' => [$shift]]],
            'weeks' => [array_map(fn ($offset) => $monday->addDays($offset), range(0, 6))],
            'start' => $monday, 'end' => $monday->addWeek(), 'periodLabel' => '12 Oct 2026 – 18 Oct 2026',
            'timezone' => 'Europe/London', 'businessName' => 'Rota print', 'businessAddress' => '10 High Street, London, E1 1AA',
        ];

        $withoutBreaks = view('rota.export', [...$data, 'showBreaks' => false])->render();
        $this->assertStringContainsString('Rota print', $withoutBreaks);
        $this->assertStringContainsString('10 High Street, London, E1 1AA', $withoutBreaks);
        $this->assertStringContainsString('Mon 12 Oct', $withoutBreaks);
        $this->assertStringContainsString('09:00–13:00', $withoutBreaks);
        $this->assertStringNotContainsString('Times shown in Europe/London', $withoutBreaks);
        $this->assertStringNotContainsString('Break: 15 min', $withoutBreaks);
        $withBreaks = view('rota.export', [...$data, 'showBreaks' => true])->render();
        $this->assertStringContainsString('Break: 15 min', $withBreaks);

        $this->actingAs($admin, 'web')->get('/rota/export?period=week&date=2026-10-12&show_breaks=1')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_rota_api_returns_published_shifts_only_and_is_tenant_scoped(): void
    {
        [$businessId, $admin, $employee] = $this->workspace('gamma');
        [, , $otherEmployee] = $this->workspace('delta');
        DB::table('rota_shifts')->insert([
            ['business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Visible shift', 'starts_at' => '2026-10-12 09:00:00', 'ends_at' => '2026-10-12 17:00:00', 'break_minutes' => 30, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $businessId, 'user_id' => $employee->id, 'title' => 'Draft shift', 'starts_at' => '2026-10-13 09:00:00', 'ends_at' => '2026-10-13 17:00:00', 'break_minutes' => 30, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $otherEmployee->business_id, 'user_id' => $otherEmployee->id, 'title' => 'Other tenant', 'starts_at' => '2026-10-14 09:00:00', 'ends_at' => '2026-10-14 17:00:00', 'break_minutes' => 30, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($employee, 'sanctum')->getJson('/api/rota')->assertOk()
            ->assertJsonCount(1, 'shifts')->assertJsonPath('shifts.0.title', 'Visible shift');
    }
}
