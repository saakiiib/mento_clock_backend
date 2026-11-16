<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Tests\TestCase;

class PlatformAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): PlatformAdmin
    {
        $admin = PlatformAdmin::create([
            'name' => 'Primary Operator', 'email' => 'primary@example.test',
            'password' => 'primary-password-123', 'active' => true,
        ]);
        $this->actingAs($admin, 'platform')->withSession(['platform_version' => $admin->auth_version]);
        Auth::shouldUse('web');
        return $admin;
    }

    public function test_guest_cannot_manage_super_admin_accounts(): void
    {
        $this->get('/platform/admins')->assertRedirect('/login');
        $this->post('/platform/admins', [])->assertRedirect('/login');
    }

    public function test_platform_admin_can_create_another_super_admin_with_phone(): void
    {
        $actor = $this->signIn();

        $this->get('/platform/admins')->assertOk()->assertSee('Create Super Admin')->assertSee('Manage existing accounts');
        $this->post('/platform/admins', [
            'name' => 'Support Operator', 'email' => ' SUPPORT@EXAMPLE.TEST ', 'phone' => '07700900123',
            'password' => 'support-password-123', 'password_confirmation' => 'support-password-123',
        ])->assertRedirect()->assertSessionHas('status');

        $created = PlatformAdmin::where('email', 'support@example.test')->firstOrFail();
        $this->assertSame('07700900123', $created->phone);
        $this->assertTrue(Hash::check('support-password-123', $created->password));
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $actor->id, 'action' => 'Platform administrator created',
        ]);
    }

    public function test_super_admin_can_update_another_account_and_revoke_its_sessions(): void
    {
        $this->signIn();
        $target = PlatformAdmin::create([
            'name' => 'Old Operator', 'email' => 'old@example.test', 'phone' => '',
            'password' => 'old-password-123', 'active' => true,
        ]);

        $this->post('/platform/admins/'.$target->id, [
            'name' => 'New Operator', 'email' => 'new@example.test', 'phone' => '07700900124', 'active' => '0',
            'password' => 'replacement-password-123', 'password_confirmation' => 'replacement-password-123',
        ])->assertRedirect()->assertSessionHas('status');

        $target->refresh();
        $this->assertSame('New Operator', $target->name);
        $this->assertSame('new@example.test', $target->email);
        $this->assertSame('07700900124', $target->phone);
        $this->assertFalse($target->active);
        $this->assertSame(2, $target->auth_version);
        $this->assertTrue(Hash::check('replacement-password-123', $target->password));
    }

    public function test_admin_cannot_deactivate_the_account_they_are_using(): void
    {
        $admin = $this->signIn();

        $this->post('/platform/admins/'.$admin->id, [
            'name' => $admin->name, 'email' => $admin->email, 'phone' => '', 'active' => '0',
        ])->assertSessionHasErrors(['active']);

        $this->assertTrue($admin->fresh()->active);
        $this->assertSame(1, DB::table('platform_admins')->where('active', true)->count());
    }

    public function test_super_admin_email_must_not_conflict_with_a_client_login(): void
    {
        $actor = $this->signIn();
        $businessId = DB::table('businesses')->insertGetId(['name' => 'Client', 'timezone' => 'Europe/London', 'active' => true]);
        User::create([
            'business_id' => $businessId, 'name' => 'Client User', 'email' => 'client@example.test',
            'password' => 'client-password-123', 'role' => 'admin', 'active' => true,
        ]);

        $this->post('/platform/admins', [
            'name' => 'Duplicate', 'email' => 'client@example.test', 'phone' => '',
            'password' => 'support-password-123', 'password_confirmation' => 'support-password-123',
        ])->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('platform_admins', 1);
        $this->assertSame('primary@example.test', $actor->fresh()->email);
    }
}
