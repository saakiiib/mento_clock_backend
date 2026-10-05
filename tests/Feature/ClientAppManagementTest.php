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
        $branch = DB::table('branches')->insertGetId(['business_id'=>$business,'name'=>'Branch '.$name,'address'=>'Test','latitude'=>52,'longitude'=>-0.7,'radius_m'=>100,'active'=>true]);
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
}
