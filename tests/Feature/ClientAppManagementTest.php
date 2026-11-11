<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientAppManagementTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name, string $role = 'employee'): array
    {
        $business = DB::table('businesses')->insertGetId(['name'=>$name,'timezone'=>'Europe/London','active'=>true]);
        foreach (DB::table('modules')->where('available',true)->get(['id']) as $module) DB::table('business_modules')->insert(['business_id'=>$business,'module_id'=>$module->id,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        $branch = DB::table('branches')->insertGetId(['business_id'=>$business,'name'=>'Branch '.$name,'address'=>'Test','latitude'=>52,'longitude'=>-0.7,'location_required'=>true,'radius_m'=>100,'active'=>true]);
        $user = User::create(['business_id'=>$business,'name'=>$name,'email'=>strtolower(str_replace(' ','-',$name)).'@example.test','password'=>'test-password-123','role'=>$role,'active'=>true]);
        if ($role === 'employee') DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$user->id,'branch_id'=>$branch]);
        return [$user, $branch];
    }

    public function test_manager_api_is_role_and_business_scoped(): void
    {
        [$admin, $branch] = $this->account('Client A', 'admin');
        [$foreignAdmin, $foreignBranch] = $this->account('Client B', 'admin');
        $employee = User::create(['business_id'=>$admin->business_id,'name'=>'Worker A','email'=>'worker-a@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
        DB::table('employee_branches')->insert(['business_id'=>$admin->business_id,'user_id'=>$employee->id,'branch_id'=>$branch]);

        $this->actingAs($admin,'sanctum')->getJson('/api/manager/dashboard')->assertOk()->assertJsonPath('business','Client A');
        $this->getJson('/api/manager/employees')->assertOk()->assertJsonCount(1,'data');
        $this->postJson('/api/manager/employees',['name'=>'New Worker','email'=>'new-worker@example.test','password'=>'secure-test-456','branches'=>[$branch]])->assertCreated();
        $this->postJson('/api/manager/employees',['name'=>'Unassigned Worker','email'=>'unassigned-worker@example.test','password'=>'Clock123'])->assertCreated();
        $unassigned = User::where('email','unassigned-worker@example.test')->firstOrFail();
        $this->assertDatabaseMissing('employee_branches',['user_id'=>$unassigned->id]);
        $this->postJson('/api/manager/employees',['name'=>'Foreign Worker','email'=>'foreign-worker@example.test','password'=>'secure-test-456','branches'=>[$foreignBranch]])->assertUnprocessable();
        $this->getJson('/api/manager/branches')->assertOk()->assertJsonCount(1,'data');
        $this->actingAs($employee,'sanctum')->getJson('/api/manager/dashboard')->assertForbidden();
        $this->actingAs($foreignAdmin,'sanctum')->getJson('/api/manager/employees')->assertOk()->assertJsonCount(0,'data');
    }

    public function test_manager_report_correction_is_audited_and_cannot_cross_businesses(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(15));
        [$admin, $branch] = $this->account('Client A', 'admin');
        [$foreignEmployee, $foreignBranch] = $this->account('Client B');
        $foreignRecord = DB::table('attendance_records')->insertGetId(['business_id'=>$foreignEmployee->business_id,'user_id'=>$foreignEmployee->id,'branch_id'=>$foreignBranch,'clock_in'=>now()->subHours(2),'clock_out'=>now()->subHour(),'in_lat'=>52,'in_lng'=>-0.7]);
        $employee = User::create(['business_id'=>$admin->business_id,'name'=>'Worker A','email'=>'worker-a@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
        $record = DB::table('attendance_records')->insertGetId(['business_id'=>$admin->business_id,'user_id'=>$employee->id,'branch_id'=>$branch,'clock_in'=>now()->subHours(2),'clock_out'=>now()->subHour(),'in_lat'=>52,'in_lng'=>-0.7]);

        $this->actingAs($admin,'sanctum')->getJson('/api/manager/reports?period=daily&date='.now()->toDateString())->assertOk()->assertJsonCount(1,'records');
        $this->postJson('/api/manager/attendance/'.$record.'/correct',['clock_in'=>now()->subHours(3)->toIso8601String(),'clock_out'=>now()->subHour()->toIso8601String(),'reason'=>'Manager approved correction'])->assertOk();
        $this->assertDatabaseHas('audit_logs',['business_id'=>$admin->business_id,'actor_id'=>$admin->id,'attendance_id'=>$record,'action'=>'corrected']);
        $this->postJson('/api/manager/attendance/'.$foreignRecord.'/correct',['clock_in'=>now()->subHours(3)->toIso8601String(),'clock_out'=>now()->subHour()->toIso8601String(),'reason'=>'Attempt foreign edit'])->assertNotFound();
    }

    public function test_manager_api_enforces_branch_and_employee_quotas_and_saves_employee_profile(): void
    {
        [$admin, $branch] = $this->account('Quota Client', 'admin');
        DB::table('client_profiles')->insert(['business_id'=>$admin->business_id,'branch_limit'=>1,'employees_per_branch_limit'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $worker = User::create(['business_id'=>$admin->business_id,'name'=>'Existing Worker','email'=>'existing-worker@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
        DB::table('employee_branches')->insert(['business_id'=>$admin->business_id,'user_id'=>$worker->id,'branch_id'=>$branch]);
        $this->actingAs($admin,'sanctum')->getJson('/api/manager/dashboard')->assertOk()->assertJsonPath('branch_limit',1);
        $this->getJson('/api/manager/branches')->assertOk()->assertJsonPath('data.0.employee_count',1)->assertJsonPath('data.0.employee_limit',1);

        $employeeLimit = $this->postJson('/api/manager/employees',[
            'name'=>'Over Limit','email'=>'over-limit@example.test','password'=>'secure-test-456','branches'=>[$branch],
        ]);
        $employeeLimit->assertUnprocessable();
        $this->assertStringContainsString('employee limit',$employeeLimit->json('errors.branches.0'));

        $branchLimit = $this->postJson('/api/manager/branches',[
            'name'=>'Second Branch','address'=>'Second','latitude'=>52,'longitude'=>-1,'radius_m'=>100,
        ]);
        $branchLimit->assertUnprocessable();
        $this->assertStringContainsString('branch limit',$branchLimit->json('errors.branch.0'));

        $this->putJson('/api/manager/employees/'.$worker->id,[
            'name'=>'Existing Worker','email'=>'existing-worker@example.test','branches'=>[$branch],
            'job_title'=>'Shift Lead','employment_start_date'=>'2026-02-01',
            'emergency_contact_name'=>'Alex Worker','emergency_contact_phone'=>'+447700900123',
        ])->assertOk();
        $this->assertDatabaseHas('users',['id'=>$worker->id,'job_title'=>'Shift Lead','employment_start_date'=>'2026-02-01','emergency_contact_name'=>'Alex Worker','emergency_contact_phone'=>'+447700900123']);
        $this->getJson('/api/manager/employees')->assertOk()->assertJsonPath('data.0.job_title','Shift Lead');
        $this->postJson('/api/auth/login',['email'=>$worker->email,'password'=>'test-password-123'])
            ->assertOk()->assertJsonPath('user.job_title','Shift Lead')->assertJsonPath('user.emergency_contact_name','Alex Worker');
    }

    public function test_manager_can_set_gps_requirement_independently_from_address_and_coordinates(): void
    {
        [$admin, $branch] = $this->account('Client A', 'admin');

        $this->actingAs($admin, 'sanctum')->putJson('/api/manager/branches/'.$branch, [
            'name' => 'Branch Client A', 'address' => '', 'latitude' => null, 'longitude' => null,
            'radius_m' => 100, 'location_required' => false,
        ])->assertOk();

        $this->assertDatabaseHas('branches', ['id' => $branch, 'address' => null, 'latitude' => null, 'longitude' => null, 'location_required' => false]);
        $this->getJson('/api/manager/branches')->assertOk()->assertJsonPath('data.0.location_required', false);

        $this->putJson('/api/manager/branches/'.$branch, [
            'name' => 'Branch Client A', 'address' => 'Workplace address', 'latitude' => null,
            'longitude' => null, 'radius_m' => 100, 'location_required' => true,
        ])->assertOk();
        $this->assertDatabaseHas('branches', ['id' => $branch, 'location_required' => true]);
        $this->getJson('/api/manager/branches')->assertOk()->assertJsonPath('data.0.location_required', true);
    }

    public function test_mobile_manager_can_review_own_business_leave_and_read_own_rota(): void
    {
        [$admin, $branch] = $this->account('Mobile Manager', 'admin');
        [$foreignAdmin] = $this->account('Other Mobile Manager', 'admin');
        $employee = User::create(['business_id'=>$admin->business_id,'name'=>'Team Member','email'=>'mobile-team-member@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
        DB::table('employee_branches')->insert(['business_id'=>$admin->business_id,'user_id'=>$employee->id,'branch_id'=>$branch]);
        $leaveId = DB::table('leave_requests')->insertGetId([
            'business_id'=>$admin->business_id,'user_id'=>$employee->id,'leave_type'=>'unpaid',
            'start_date'=>now()->addDays(30)->toDateString(),'end_date'=>now()->addDays(30)->toDateString(),
            'working_days'=>1,'reason'=>'Personal appointment','status'=>'pending','created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->actingAs($admin,'sanctum')->getJson('/api/manager/leave')->assertOk()
            ->assertJsonCount(1,'data')->assertJsonPath('data.0.employee_name','Team Member');
        $this->postJson('/api/manager/leave/requests/'.$leaveId.'/review',['decision'=>'approved'])->assertOk()->assertJsonPath('status','approved');
        $this->assertDatabaseHas('leave_requests',['id'=>$leaveId,'business_id'=>$admin->business_id,'status'=>'approved','reviewed_by'=>$admin->id]);

        $week = now()->addWeeks(2)->startOfWeek();
        $this->getJson('/api/manager/rota?week='.$week->toDateString())->assertOk()
            ->assertJsonPath('employees.0.name','Team Member')->assertJsonPath('branches.0.name','Branch Mobile Manager');
        $created = $this->postJson('/api/manager/rota/shifts',[
            'user_id'=>$employee->id,'branch_id'=>$branch,'title'=>'Mobile shift',
            'starts_at'=>$week->toDateString().'T09:00','ends_at'=>$week->toDateString().'T17:00',
            'break_minutes'=>30,'notes'=>'',
        ])->assertCreated();
        $shiftId = $created->json('id');
        $this->postJson('/api/manager/rota/publish-week',['week'=>$week->toDateString()])->assertOk()->assertJsonPath('published',1);
        $this->assertDatabaseHas('rota_shifts',['id'=>$shiftId,'business_id'=>$admin->business_id,'status'=>'published']);
        $this->actingAs($foreignAdmin,'sanctum')->getJson('/api/manager/leave')->assertOk()->assertJsonCount(0,'data');
        $this->actingAs($foreignAdmin,'sanctum')->postJson('/api/manager/leave/requests/'.$leaveId.'/review',['decision'=>'rejected'])->assertNotFound();
    }
}
