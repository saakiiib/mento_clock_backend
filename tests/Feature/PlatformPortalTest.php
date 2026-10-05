<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Tests\TestCase;

class PlatformPortalTest extends TestCase
{
    use RefreshDatabase;

    private function platform(): PlatformAdmin
    {
        $admin = PlatformAdmin::create(['name'=>'Mento Operator','email'=>'operator@example.test','password'=>'platform-password-123','active'=>true]);
        $this->actingAs($admin, 'platform')->withSession(['platform_version'=>$admin->auth_version]);
        // The production platform middleware deliberately does not switch the default guard.
        Auth::shouldUse('web');
        return $admin;
    }

    private function client(string $name='Client A'): User
    {
        $id = DB::table('businesses')->insertGetId(['name'=>$name,'timezone'=>'Europe/London','active'=>true]);
        return User::create(['name'=>$name.' Admin','business_id'=>$id,'email'=>strtolower(str_replace(' ','',$name)).'@example.test','password'=>'client-password-123','role'=>'admin','active'=>true]);
    }

    private function profile(): array
    {
        return ['name'=>'New Business','timezone'=>'Europe/London','contact_name'=>'Client Contact','contact_email'=>'contact@example.test','contact_phone'=>'07700900123','address'=>'Test address','website'=>'https://example.com','plan_label'=>'Multiple workplaces','notes'=>'Internal note only'];
    }

    public function test_guest_and_client_cannot_access_platform_management(): void
    {
        $this->get('/platform')->assertRedirect('/login');
        $client = $this->client();
        $this->actingAs($client)->get('/platform/clients/create')->assertRedirect('/login');
        $this->post('/platform/clients', $this->profile())->assertRedirect('/login');
        $this->assertDatabaseCount('businesses',1);
    }

    public function test_shared_login_routes_each_account_to_the_correct_console(): void
    {
        $client = $this->client();
        PlatformAdmin::create(['name'=>'Operator','email'=>'operator@example.test','password'=>'platform-password-123','active'=>true]);
        $this->post('/login',['email'=>$client->email,'password'=>'client-password-123'])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login',['email'=>'operator@example.test','password'=>'platform-password-123'])->assertRedirect('/platform');
        $this->get('/platform')->assertOk()->assertSee('Every client. One clear view.');
        $this->post('/platform/logout')->assertRedirect('/login');
        $this->get('/platform')->assertRedirect('/login');
    }

    public function test_super_admin_creates_a_client_and_business_admin_atomically(): void
    {
        $this->platform();
        $this->get('/platform/clients/create')->assertOk();
        $this->post('/platform/clients',$this->profile()+['admin_name'=>'Client Admin','admin_email'=>'clientadmin@example.test','password'=>'client-password-123','password_confirmation'=>'client-password-123'])->assertRedirect();
        $business = DB::table('businesses')->where('name','New Business')->first();
        $this->assertDatabaseHas('client_profiles',['business_id'=>$business->id,'plan_label'=>'Multiple workplaces']);
        $user = User::where('email','clientadmin@example.test')->firstOrFail();
        $this->assertSame('admin',$user->role);
        $this->assertSame($business->id,$user->business_id);
        $this->assertTrue(Hash::check('client-password-123',$user->password));
        $this->get('/platform/clients/'.$business->id)->assertOk()->assertSee('New Business');
        $this->assertDatabaseHas('platform_audit_logs',['business_id'=>$business->id,'action'=>'Client created']);
        $this->assertStringNotContainsString('client-password-123',DB::table('platform_audit_logs')->value('details'));
    }

    public function test_invalid_client_creation_does_not_leave_partial_accounts(): void
    {
        $this->platform();
        $this->post('/platform/clients',$this->profile()+['admin_name'=>'Invalid','admin_email'=>'operator@example.test','password'=>'short','password_confirmation'=>'short'])->assertSessionHasErrors(['admin_email','password']);
        $this->assertDatabaseCount('businesses',0);
        $this->assertDatabaseCount('client_profiles',0);
    }

    public function test_existing_clients_can_receive_profiles_and_internal_notes_stay_private(): void
    {
        $client=$this->client();$this->platform();
        $this->post('/platform/clients/'.$client->business_id,$this->profile())->assertRedirect();
        $this->assertDatabaseHas('businesses',['id'=>$client->business_id,'name'=>'New Business']);
        Auth::guard('platform')->logout();
        $this->actingAs($client,'web')->get('/company')->assertOk()->assertSee('Client Contact')->assertDontSee('Internal note only');
        foreach(['/','/people','/workplaces','/attendance','/reports']as $url)$this->get($url)->assertOk();
    }

    public function test_suspension_revokes_mobile_access_and_preserves_client_records(): void
    {
        $client=$this->client();$client->createToken('mobile');
        DB::table('sessions')->insert(['id'=>'client-session','user_id'=>$client->id,'payload'=>'test','last_activity'=>time()]);
        $this->platform();
        $this->post('/platform/clients/'.$client->business_id.'/status',['active'=>0])->assertRedirect();
        $this->assertDatabaseHas('businesses',['id'=>$client->business_id,'active'=>false]);
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->assertDatabaseMissing('sessions',['id'=>'client-session']);
        $this->assertDatabaseHas('users',['id'=>$client->id]);
        $this->actingAs($client,'web')->get('/')->assertForbidden();
        $this->post('/platform/clients/'.$client->business_id.'/status',['active'=>1])->assertRedirect();
        $this->assertDatabaseHas('businesses',['id'=>$client->business_id,'active'=>true]);
    }

    public function test_admin_update_is_scoped_and_last_admin_cannot_be_disabled(): void
    {
        $a=$this->client('Client A');$b=$this->client('Client B');$this->platform();
        $values=['name'=>'Updated','email'=>$b->email,'active'=>1];
        $this->post('/platform/clients/'.$a->business_id.'/admins/'.$b->id,$values)->assertNotFound();
        $this->post('/platform/clients/'.$a->business_id.'/admins/'.$a->id,['name'=>$a->name,'email'=>$a->email,'active'=>0])->assertUnprocessable();
        $a->createToken('mobile');
        $this->post('/platform/clients/'.$a->business_id.'/admins/'.$a->id,['name'=>'New Name','email'=>$a->email,'active'=>1,'password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-123',$a->fresh()->password));
        $this->assertSame(0,$a->tokens()->count());
    }

    public function test_platform_sessions_are_revoked_by_password_change_or_deactivation(): void
    {
        $admin=$this->platform();
        $this->post('/platform/security',['current_password'=>'wrong','password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertSessionHasErrors('current_password');
        $this->post('/platform/security',['current_password'=>'platform-password-123','password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertRedirect();
        $this->get('/platform')->assertOk();
        $this->withSession(['platform_version'=>1])->get('/platform')->assertRedirect('/login');
        $this->actingAs($admin->fresh(),'platform')->withSession(['platform_version'=>2]);Auth::shouldUse('web');
        $admin->update(['active'=>false]);
        Auth::guard('platform')->setUser($admin->fresh());
        $this->get('/platform')->assertRedirect('/login');
    }

    public function test_client_dashboard_does_not_show_another_business_data(): void
    {
        $a=$this->client('First Business');$b=$this->client('Second Business');
        User::create(['name'=>'Private Other Employee','business_id'=>$b->business_id,'email'=>'private@example.test','password'=>'test-password-123','role'=>'employee','active'=>true]);
        $this->actingAs($a)->get('/people')->assertOk()->assertDontSee('Private Other Employee')->assertDontSee('Second Business');
    }

    public function test_platform_account_cannot_use_employee_api_or_business_console(): void
    {
        $this->platform();
        $this->get('/')->assertRedirect('/login');
        $this->postJson('/api/v1/auth/login',['email'=>'operator@example.test','password'=>'platform-password-123'])->assertUnauthorized();
    }
}
