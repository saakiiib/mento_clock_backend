<?php
namespace Tests\Feature;
use App\Models\{PlatformAdmin, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth,DB};
use Tests\TestCase;

class V38StandingSupportTest extends TestCase
{
 use RefreshDatabase;
 private function fixture():array {
  $business=DB::table('businesses')->insertGetId(['name'=>'Trial Cafe','timezone'=>'Europe/London','active'=>true]);
  $other=DB::table('businesses')->insertGetId(['name'=>'Other Cafe','timezone'=>'Europe/London','active'=>true]);
  $owner=User::create(['business_id'=>$business,'name'=>'Owner','email'=>'owner-standing@example.test','password'=>'password-123456','role'=>'admin','active'=>true]);
  $platform=PlatformAdmin::create(['name'=>'Super Admin','email'=>'standing-admin@example.test','password'=>'password-123456','active'=>true]);
  return compact('business','other','owner','platform');
 }
 private function platform(PlatformAdmin $admin):void {
  $this->actingAs($admin,'platform')->withSession(['platform_version'=>$admin->auth_version]);
  Auth::shouldUse('web');
 }
 public function test_ongoing_access_disabled_by_default_and_cross_business_access_denied():void {
  $d=$this->fixture();$this->platform($d['platform']);
  $this->get('/platform/assist/manage/'.$d['business'])->assertForbidden();
  $this->get('/platform/assist/manage/'.$d['other'])->assertForbidden();
  $this->post('/platform/assist/manage/'.$d['business'].'/branch',[
    'name'=>'No consent', 'radius_m'=>100,'location_required'=>0,'reason'=>'No authorisation'])->assertForbidden();
  $this->assertDatabaseCount('branches',0);
 }
 public function test_client_can_grant_and_revoke_and_support_actions_are_audited():void {
  $d=$this->fixture();$this->actingAs($d['owner'],'web');
  $this->post('/assistance/standing-access',['decision'=>'grant','acknowledge'=>1])->assertRedirect();
  $this->assertDatabaseHas('client_support_authorizations',['business_id'=>$d['business'],'revoked_at'=>null]);
  $this->platform($d['platform']);
  $this->get('/platform/assist/manage/'.$d['business'])->assertOk();
  $this->post('/platform/assist/manage/'.$d['business'].'/branch',[
    'name'=>'New branch','radius_m'=>100,'location_required'=>0,'reason'=>'Client requested onboarding'])->assertRedirect();
  $this->assertDatabaseHas('branches',['business_id'=>$d['business'],'name'=>'New branch']);
  $this->assertDatabaseHas('client_standing_support_events',['business_id'=>$d['business'],'action'=>'branch_created']);
  $this->assertDatabaseCount('attendance_records',0);
  $this->actingAs($d['owner'],'web');
  $this->post('/assistance/standing-access',['decision'=>'revoke'])->assertRedirect();
  $this->platform($d['platform']);
  $this->get('/platform/assist/manage/'.$d['business'])->assertForbidden();
  $this->post('/platform/assist/manage/'.$d['business'].'/branch',[
    'name'=>'After revoked','radius_m'=>100,'location_required'=>0,'reason'=>'No longer authorised'])->assertForbidden();
  $this->assertDatabaseMissing('branches',['name'=>'After revoked']);
 }
 public function test_restricted_operator_cannot_use_ongoing_access():void {
  $d=$this->fixture();
  DB::table('client_support_authorizations')->insert([
    'business_id'=>$d['business'],'granted_by'=>$d['owner']->id,'granted_at'=>now('UTC'),'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
  $operator=PlatformAdmin::create(['name'=>'Operator','email'=>'operator-standing@example.test','password'=>'password-123456','active'=>true]);
  DB::table('platform_support_operators')->insert(['admin_id'=>$operator->id,'assigned_by'=>$d['platform']->id,'created_at'=>now('UTC')]);
  $this->platform($operator);
  $this->get('/platform/assist/manage/'.$d['business'])->assertForbidden();
 }
}
