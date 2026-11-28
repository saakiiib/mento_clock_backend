<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class V39NotificationsTest extends TestCase {
 use RefreshDatabase;
 private function workspace(string $key): array {
  $bid=DB::table('businesses')->insertGetId(['name'=>$key,'timezone'=>'Europe/London','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $admin=User::create(['business_id'=>$bid,'name'=>'Admin '.$key,'email'=>$key.'-admin@example.test','password'=>'secure-test-password','role'=>'admin','active'=>true]);
  $worker=User::create(['business_id'=>$bid,'name'=>'Worker '.$key,'email'=>$key.'-worker@example.test','password'=>'secure-test-password','role'=>'employee','active'=>true]);
  return [$bid,$admin,$worker];
 }
 public function test_business_notification_isolated_and_acknowledged(): void {
  [$a,$admin,$worker]=$this->workspace('v39a');[,,$other]=$this->workspace('v39b');
  $this->actingAs($admin)->post('/notifications',['title'=>'Please check your details','body'=>'Open your profile and update email.','action_type'=>'profile','target_type'=>'business','requires_ack'=>1])->assertRedirect();
  $mid=DB::table('v39_messages')->where('business_id',$a)->value('id');$this->assertNotNull($mid);
  $this->actingAs($worker,'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonCount(1,'data');
  $recipient=DB::table('v39_recipients')->where('message_id',$mid)->where('user_id',$worker->id)->firstOrFail();
  $this->actingAs($other,'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonCount(0,'data');
  $this->actingAs($other,'sanctum')->postJson('/api/notifications/'.$recipient->id.'/acknowledge')->assertNotFound();
  $this->actingAs($worker,'sanctum')->postJson('/api/notifications/'.$recipient->id.'/acknowledge')->assertOk();
  $this->assertNotNull(DB::table('v39_recipients')->where('id',$recipient->id)->value('acknowledged_at'));
 }
 public function test_feedback_is_scoped_to_own_notification(): void {
  [$a,$admin,$worker]=$this->workspace('v39c');[,,$other]=$this->workspace('v39d');
  $this->actingAs($admin)->post('/notifications',['title'=>'Your feedback matters','body'=>'Share product feedback with us.','action_type'=>'feedback','target_type'=>'business'])->assertRedirect();
  $rid=DB::table('v39_recipients')->where('user_id',$worker->id)->value('id');
  $this->actingAs($other,'sanctum')->postJson('/api/notifications/feedback',['comment'=>'An improvement idea','recipient_id'=>$rid])->assertForbidden();
  $this->actingAs($worker,'sanctum')->postJson('/api/notifications/feedback',['comment'=>'I would like an improved clock-in screen','recipient_id'=>$rid])->assertCreated();
  $this->assertDatabaseHas('v39_feedback',['business_id'=>$a,'user_id'=>$worker->id]);
 }
 public function test_scheduled_message_does_not_publish_early(): void {
  [$a,$admin,$worker]=$this->workspace('v39e');
  $this->actingAs($admin)->post('/notifications',['title'=>'Scheduled message','body'=>'A planned announcement for employees.','action_type'=>'none','target_type'=>'business','scheduled_at'=>now()->addDay()->format('Y-m-d H:i:s')])->assertRedirect();
  $this->assertDatabaseCount('v39_recipients',0);
  $this->assertSame('draft',DB::table('v39_messages')->where('business_id',$a)->value('status'));
 }
}
