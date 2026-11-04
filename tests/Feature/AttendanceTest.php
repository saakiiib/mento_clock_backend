<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AttendanceTest extends TestCase {
 use RefreshDatabase;
 private function account(string $suffix, bool $withAddress = true): array {
  $business=DB::table('businesses')->insertGetId(['name'=>$suffix,'timezone'=>'Europe/London','active'=>true]);
  foreach (DB::table('modules')->where('available',true)->get(['id']) as $module) DB::table('business_modules')->insert(['business_id'=>$business,'module_id'=>$module->id,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
  $branch=DB::table('branches')->insertGetId(['business_id'=>$business,'name'=>'Branch '.$suffix,'address'=>$withAddress?'Test only':null,'latitude'=>$withAddress?52:null,'longitude'=>$withAddress?-0.7:null,'radius_m'=>100,'active'=>true]);
  $u=User::create(['business_id'=>$business,'name'=>$suffix,'email'=>$suffix.'@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
  DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$u->id,'branch_id'=>$branch]);return [$u,$branch];
 }
 private function gps(int $branch): array {return ['branch_id'=>$branch,'latitude'=>52,'longitude'=>-0.7,'accuracy_m'=>5,'captured_at'=>now()->toIso8601String(),'confirm_off_rota'=>true];}
 public function test_employee_cannot_clock_into_another_business(): void {
  [$a,$ba]=$this->account('a');[$b,$bb]=$this->account('b');
  $this->actingAs($a,'sanctum')->postJson('/api/attendance/clock-in',$this->gps($bb))->assertForbidden();$this->assertDatabaseCount('attendance_records',0);
 }
 public function test_duplicate_clock_in_is_rejected_and_repeat_clock_out_is_idempotent(): void {
  [$u,$branch]=$this->account('a');$this->actingAs($u,'sanctum')->postJson('/api/attendance/clock-in',$this->gps($branch))->assertCreated();
  $this->postJson('/api/attendance/clock-in',$this->gps($branch))->assertConflict();$this->assertDatabaseCount('attendance_records',1);
  $id=DB::table('attendance_records')->value('id');$this->postJson('/api/attendance/'.$id.'/clock-out',$this->gps($branch))->assertOk();$first=DB::table('attendance_records')->value('clock_out');$this->travel(5)->minutes();$this->postJson('/api/attendance/'.$id.'/clock-out',$this->gps($branch))->assertOk();$this->assertSame($first,DB::table('attendance_records')->value('clock_out'));
 }
   public function test_location_outside_geofence_still_clocks_in(): void {
   [$u,$b]=$this->account('a');$v=$this->gps($b);$v['latitude']=53;$v['confirm_off_rota']=true;$this->actingAs($u,'sanctum')->postJson('/api/attendance/clock-in',$v)->assertCreated()->assertJsonPath('outside',true);$this->assertDatabaseCount('attendance_records',1);
  }
 public function test_clock_in_without_location_is_allowed_when_workplace_has_no_address(): void {
  [$u,$branch]=$this->account('a',false);
  $this->actingAs($u,'sanctum')->postJson('/api/attendance/clock-in',['branch_id'=>$branch,'confirm_off_rota'=>true])->assertCreated();
  $this->assertDatabaseCount('attendance_records',1);
  $this->assertNull(DB::table('attendance_records')->value('in_lat'));
 }
 public function test_clock_in_without_location_is_rejected_when_workplace_has_address(): void {
  [$u,$branch]=$this->account('a');
  $this->actingAs($u,'sanctum')->postJson('/api/attendance/clock-in',['branch_id'=>$branch,'confirm_off_rota'=>true])->assertUnprocessable()->assertJsonValidationErrors('location');
  $this->assertDatabaseCount('attendance_records',0);
 }
 public function test_account_marks_location_requirement_per_workplace(): void {
  [$withAddress]=$this->account('a');
  [$withoutAddress]=$this->account('b',false);
  $this->actingAs($withAddress,'sanctum')->getJson('/api/me')->assertJsonPath('user.branches.0.location_required',true);
  $this->actingAs($withoutAddress,'sanctum')->getJson('/api/me')->assertJsonPath('user.branches.0.location_required',false);
 }
 public function test_history_and_clock_out_are_tenant_scoped(): void {
  [$a,$ba]=$this->account('a');[$b,$bb]=$this->account('b');$this->actingAs($a,'sanctum')->postJson('/api/attendance/clock-in',$this->gps($ba))->assertCreated();$id=DB::table('attendance_records')->value('id');
  $this->actingAs($b,'sanctum')->getJson('/api/attendance')->assertJsonCount(0,'data');$this->postJson('/api/attendance/'.$id.'/clock-out',$this->gps($bb))->assertNotFound();
 }
 public function test_employee_cannot_open_admin_console(): void {
  [$u,$b]=$this->account('a');$this->actingAs($u,'web')->get('/')->assertForbidden();
 }
 public function test_login_issues_revocable_bearer_token(): void {
  [$u,$b]=$this->account('a');$token=$this->postJson('/api/auth/login',['email'=>$u->email,'password'=>'test-password-123'])->assertOk()->json('token');
  $this->withToken($token)->getJson('/api/attendance')->assertOk();
  $this->withToken($token)->postJson('/api/auth/logout')->assertOk();$this->assertDatabaseCount('personal_access_tokens',0);
 }
}
