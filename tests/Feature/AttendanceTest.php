<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AttendanceTest extends TestCase {
 use RefreshDatabase;
 private function account(string $suffix): array {
  $business=DB::table('businesses')->insertGetId(['name'=>$suffix,'timezone'=>'Europe/London','active'=>true]);
  $branch=DB::table('branches')->insertGetId(['business_id'=>$business,'name'=>'Branch '.$suffix,'address'=>'Test only','latitude'=>52,'longitude'=>-0.7,'radius_m'=>100,'active'=>true]);
  $u=User::create(['business_id'=>$business,'name'=>$suffix,'email'=>$suffix.'@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
  DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$u->id,'branch_id'=>$branch]);return [$u,$branch];
 }
 private function gps(int $branch): array {return ['branch_id'=>$branch,'latitude'=>52,'longitude'=>-0.7,'accuracy_m'=>5,'captured_at'=>now()->toIso8601String()];}
 public function test_employee_cannot_clock_into_another_business(): void {
  [$a,$ba]=$this->account('a');[$b,$bb]=$this->account('b');
  $this->actingAs($a,'api')->postJson('/api/v1/attendance/clock-in',$this->gps($bb))->assertForbidden();$this->assertDatabaseCount('attendance_records',0);
 }
 public function test_duplicate_clock_in_is_rejected_and_repeat_clock_out_is_idempotent(): void {
  [$u,$branch]=$this->account('a');$this->actingAs($u,'api')->postJson('/api/v1/attendance/clock-in',$this->gps($branch))->assertCreated();
  $this->postJson('/api/v1/attendance/clock-in',$this->gps($branch))->assertConflict();$this->assertDatabaseCount('attendance_records',1);
  $id=DB::table('attendance_records')->value('id');$this->postJson('/api/v1/attendance/'.$id.'/clock-out',$this->gps($branch))->assertOk();$first=DB::table('attendance_records')->value('clock_out');$this->travel(5)->minutes();$this->postJson('/api/v1/attendance/'.$id.'/clock-out',$this->gps($branch))->assertOk();$this->assertSame($first,DB::table('attendance_records')->value('clock_out'));
 }
 public function test_location_outside_geofence_is_rejected(): void {
  [$u,$b]=$this->account('a');$v=$this->gps($b);$v['latitude']=53;$this->actingAs($u,'api')->postJson('/api/v1/attendance/clock-in',$v)->assertUnprocessable();$this->assertDatabaseCount('attendance_records',0);
 }
 public function test_history_and_clock_out_are_tenant_scoped(): void {
  [$a,$ba]=$this->account('a');[$b,$bb]=$this->account('b');$this->actingAs($a,'api')->postJson('/api/v1/attendance/clock-in',$this->gps($ba))->assertCreated();$id=DB::table('attendance_records')->value('id');
  $this->actingAs($b,'api')->getJson('/api/v1/attendance')->assertJsonCount(0,'data');$this->postJson('/api/v1/attendance/'.$id.'/clock-out',$this->gps($bb))->assertNotFound();
 }
 public function test_employee_cannot_open_admin_console(): void {
  [$u,$b]=$this->account('a');$this->actingAs($u,'web')->get('/')->assertForbidden();
 }
 public function test_login_issues_revocable_bearer_token(): void {
  [$u,$b]=$this->account('a');$token=$this->postJson('/api/v1/auth/login',['email'=>$u->email,'password'=>'test-password-123'])->assertOk()->json('token');
  $this->withToken($token)->getJson('/api/v1/attendance')->assertOk();
  $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();$this->assertDatabaseCount('api_tokens',0);
 }
}
