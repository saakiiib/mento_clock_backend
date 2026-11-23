<?php
namespace Tests\Feature;
use App\Models\{PlatformAdmin,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth,DB};
use Tests\TestCase;

class V38AssistanceTest extends TestCase
{
 use RefreshDatabase;
 private function data():array {
  $business=DB::table('businesses')->insertGetId(['name'=>'Test trial cafe','timezone'=>'Europe/London','active'=>true]);
  $branch=DB::table('branches')->insertGetId(['business_id'=>$business,'name'=>'Main','address'=>'Test','latitude'=>null,'longitude'=>null,'radius_m'=>100,'location_required'=>false,'active'=>true]);
  $owner=User::create(['business_id'=>$business,'name'=>'Client Manager','email'=>'client-v38@example.test','password'=>'secret-password-123','role'=>'admin','active'=>true]);
  $employee=User::create(['business_id'=>$business,'name'=>'Example Employee','email'=>'worker-v38@example.test','password'=>'secret-password-123','role'=>'employee','active'=>true]);
  DB::table('employee_branches')->insert(['business_id'=>$business,'user_id'=>$employee->id,'branch_id'=>$branch]);
  $platform=PlatformAdmin::create(['name'=>'Platform Owner','email'=>'platform-v38@example.test','password'=>'secret-password-123','active'=>true]);
  return compact('business','branch','owner','employee','platform');
 }
 private function platform(PlatformAdmin $admin):void {$this->actingAs($admin,'platform')->withSession(['platform_version'=>$admin->auth_version]);Auth::shouldUse('web');}
 public function test_guest_cannot_open_assistance():void {$this->get('/platform/assist')->assertRedirect('/login');$this->get('/assistance')->assertRedirect('/login');}
 public function test_support_requires_explicit_client_request():void {
  $d=$this->data();$this->platform($d['platform']);
  $this->get('/platform/assist')->assertOk();
  $this->assertDatabaseCount('client_assistance_sessions',0);
  $this->post('/platform/assist/sessions/999/branch',['name'=>'Forbidden'])->assertNotFound();
 }
 public function test_client_can_request_and_revoke_scoped_assistance():void {
  $d=$this->data();$this->actingAs($d['owner'],'web');
  $this->post('/assistance/request',['purpose'=>'Please help set up a second workplace.'])->assertRedirect();
  $session=DB::table('client_assistance_sessions')->where('business_id',$d['business'])->first();
  $this->assertSame('pending',$session->status);
  $this->post('/assistance/'.$session->id.'/revoke')->assertRedirect();
  $this->assertDatabaseHas('client_assistance_sessions',['id'=>$session->id,'status'=>'revoked']);
 }
 public function test_approved_session_can_add_workplace_and_audit_without_touching_attendance():void {
  $d=$this->data();
  $session=DB::table('client_assistance_sessions')->insertGetId(['business_id'=>$d['business'],'requested_by'=>$d['owner']->id,'operator_id'=>$d['platform']->id,'approved_by'=>$d['owner']->id,'status'=>'active','purpose'=>'Assist with onboarding','expires_at'=>now('UTC')->addHour(),'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  $this->platform($d['platform']);
  $this->post('/platform/assist/sessions/'.$session.'/branch',['name'=>'Second branch','address'=>'Example Road','radius_m'=>100,'location_required'=>0,'reason'=>'Client requested branch setup'])->assertRedirect();
  $this->assertDatabaseHas('branches',['business_id'=>$d['business'],'name'=>'Second branch']);
  $this->assertDatabaseHas('client_assistance_events',['business_id'=>$d['business'],'session_id'=>$session,'action'=>'branch_created']);
  $this->assertDatabaseCount('attendance_records',0);
 }
 public function test_restricted_operator_cannot_access_billing_or_admin_management():void {
  $d=$this->data();
  $operator=PlatformAdmin::create(['name'=>'Restricted Operator','email'=>'restricted-v38@example.test','password'=>'secret-password-123','active'=>true]);
  DB::table('platform_support_operators')->insert(['admin_id'=>$operator->id,'assigned_by'=>$d['platform']->id,'created_at'=>now('UTC')]);
  $this->platform($operator);
  $this->get('/platform/assist')->assertOk();
  $this->get('/platform/admins')->assertForbidden();
  $this->get('/platform/clients/'.$d['business'])->assertForbidden();
  $this->get('/platform/attendance-monitor')->assertForbidden();
 }
 public function test_assisted_transfer_cannot_interrupt_open_shift():void {
  $d=$this->data();
  $other=DB::table('branches')->insertGetId(['business_id'=>$d['business'],'name'=>'Backup','address'=>'Test','latitude'=>null,'longitude'=>null,'radius_m'=>100,'location_required'=>false,'active'=>true]);
  DB::table('attendance_records')->insert(['business_id'=>$d['business'],'user_id'=>$d['employee']->id,'branch_id'=>$d['branch'],'clock_in'=>now('UTC'),'clock_out'=>null,'in_lat'=>null,'in_lng'=>null,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  $session=DB::table('client_assistance_sessions')->insertGetId(['business_id'=>$d['business'],'requested_by'=>$d['owner']->id,'operator_id'=>$d['platform']->id,'status'=>'active','purpose'=>'Transfer assistance','expires_at'=>now('UTC')->addHour(),'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  $this->platform($d['platform']);
  $this->post('/platform/assist/sessions/'.$session.'/assign/'.$d['employee']->id,['branch_ids'=>[$other],'reason'=>'Client requested transfer'])->assertStatus(422);
  $this->assertDatabaseHas('employee_branches',['user_id'=>$d['employee']->id,'branch_id'=>$d['branch']]);
 }
}
