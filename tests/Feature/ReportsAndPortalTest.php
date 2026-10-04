<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\AttendanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Mail,Notification,Password};
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;
class ReportsAndPortalTest extends TestCase {
 use RefreshDatabase;
 private function account(string $suffix,string $role='employee'): array {
  $b=DB::table('businesses')->insertGetId(['name'=>$suffix,'timezone'=>'Europe/London','active'=>true]);
  $branch=DB::table('branches')->insertGetId(['business_id'=>$b,'name'=>'Branch '.$suffix,'address'=>'Test address','latitude'=>52,'longitude'=>-0.7,'radius_m'=>100,'active'=>true]);
  $u=User::create(['business_id'=>$b,'name'=>$suffix,'email'=>$suffix.'@example.test','password'=>'test-password-123','role'=>$role,'active'=>true]);DB::table('employee_branches')->insert(['business_id'=>$b,'user_id'=>$u->id,'branch_id'=>$branch]);return [$u,$branch];
 }
 private function record(User $u,int $branch,string $in,?string $out): int {return DB::table('attendance_records')->insertGetId(['business_id'=>$u->business_id,'user_id'=>$u->id,'branch_id'=>$branch,'clock_in'=>$in,'clock_out'=>$out,'in_lat'=>52,'in_lng'=>-0.7]);}
 public function test_overnight_record_is_split_in_business_timezone(): void {
  [$u,$branch]=$this->account('a');$this->record($u,$branch,'2026-10-02 21:00:00','2026-10-03 02:00:00');$this->travelTo(now()->setDate(2026,10,5));
  $s=app(AttendanceReport::class);$a=$s->make($u->business_id,['period'=>'daily','date'=>'2026-10-02']);$b=$s->make($u->business_id,['period'=>'daily','date'=>'2026-10-03']);$week=$s->make($u->business_id,['period'=>'weekly','date'=>'2026-10-03']);
  $this->assertSame(7200,$a['total_seconds']);$this->assertSame(10800,$b['total_seconds']);$this->assertSame(18000,$week['total_seconds']);$this->assertSame($week['total_seconds'],array_sum(array_column($week['daily'],'seconds')));
 }
 public function test_dst_change_uses_elapsed_seconds(): void {
  [$u,$branch]=$this->account('a');$this->record($u,$branch,'2026-10-25 00:00:00','2026-10-25 03:00:00');$this->travelTo(now()->setDate(2026,10,26));$r=app(AttendanceReport::class)->make($u->business_id,['period'=>'daily','date'=>'2026-10-25']);$this->assertSame(10800,$r['total_seconds']);
 }
 public function test_employee_report_cannot_request_someone_elses_records(): void {
  [$u,$branch]=$this->account('a');$v=User::create(['business_id'=>$u->business_id,'name'=>'Other','email'=>'other@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);$this->record($v,$branch,'2026-10-02 08:00:00','2026-10-02 10:00:00');
  $this->actingAs($u,'sanctum')->getJson('/api/v1/reports?period=daily&date=2026-10-02&employee_id='.$v->id)->assertOk()->assertJsonCount(0,'records');
 }
 public function test_admin_portal_and_report_views_render(): void {
  [$u,$branch]=$this->account('a','admin');$this->record($u,$branch,'2026-10-02 08:00:00','2026-10-02 10:00:00');$this->actingAs($u)->get('/')->assertOk()->assertSee('Your people');$this->get('/reports?period=monthly&date=2026-10-02')->assertOk()->assertSee('Attendance reports');$this->get('/employees/'.$u->id.'/edit')->assertOk();$this->get('/branches/'.$branch.'/edit')->assertOk();
 }
 public function test_admin_updates_employee_and_revokes_mobile_sessions(): void {
  [$admin,$branch]=$this->account('a','admin');$user=User::create(['business_id'=>$admin->business_id,'name'=>'Worker','email'=>'worker@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);$user->createToken('mobile');
  $this->actingAs($admin)->post('/employees/'.$user->id.'/edit',['name'=>'Updated Worker','email'=>'worker-new@example.test','phone'=>'+44 7700 900123','employee_code'=>'EMP-002','branches'=>[$branch]])->assertRedirect('/');
  $this->assertSame('Updated Worker',$user->fresh()->name);$this->assertDatabaseHas('employee_branches',['user_id'=>$user->id,'branch_id'=>$branch]);$this->assertSame(0,$user->tokens()->count());
 }
 public function test_admin_cannot_edit_another_business_employee(): void {
  [$u,$branch]=$this->account('a','admin');[$v,$other]=$this->account('b');$this->actingAs($u)->post('/employees/'.$v->id.'/edit',['name'=>'Changed','email'=>$v->email,'branches'=>[$branch]])->assertNotFound();$this->assertSame('b',$v->fresh()->name);
 }
 public function test_pdf_and_csv_downloads_and_report_email(): void {
  config(['mail.default'=>'array']);[$u,$branch]=$this->account('a','admin');$this->record($u,$branch,'2026-10-02 08:00:00','2026-10-02 10:00:00');$this->actingAs($u)->get('/reports/download?period=daily&date=2026-10-02&format=pdf')->assertOk()->assertHeader('content-type','application/pdf');$this->get('/reports/download?period=daily&date=2026-10-02')->assertOk()->assertSee('Hours in period');$this->post('/reports/email',['period'=>'daily','date'=>'2026-10-02'])->assertRedirect();$messages=Mail::mailer()->getSymfonyTransport()->messages();$this->assertCount(1,$messages);$email=$messages->first()->getOriginalMessage();$this->assertSame($u->email,$email->getTo()[0]->getAddress());$this->assertCount(2,$email->getAttachments());
 }
 public function test_password_reset_revokes_tokens_and_does_not_reveal_unknown_accounts(): void {
  Notification::fake();[$u,$branch]=$this->account('a');$u->createToken('test');$known=$this->postJson('/api/v1/auth/forgot-password',['email'=>$u->email])->assertOk()->json('message');$unknown=$this->postJson('/api/v1/auth/forgot-password',['email'=>'unknown@example.test'])->assertOk()->json('message');$this->assertSame($known,$unknown);Notification::assertSentTo($u,ResetPassword::class);
  $token=Password::createToken($u);$this->post('/reset-password',['token'=>$token,'email'=>$u->email,'password'=>'new-password-456','password_confirmation'=>'new-password-456'])->assertRedirect('/login');$this->assertDatabaseCount('personal_access_tokens',0);$this->postJson('/api/v1/auth/login',['email'=>$u->email,'password'=>'new-password-456'])->assertOk();
 }
 public function test_inactive_business_cannot_use_existing_token(): void {
  [$u,$branch]=$this->account('a');$token=$u->createToken('test')->plainTextToken;DB::table('businesses')->where('id',$u->business_id)->update(['active'=>false]);$this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
 }
}
