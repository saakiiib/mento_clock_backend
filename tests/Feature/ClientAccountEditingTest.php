<?php

namespace Tests\Feature;

use App\Models\{PlatformAdmin, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Tests\TestCase;

class ClientAccountEditingTest extends TestCase
{
    use RefreshDatabase;

    private function clientAccount(): array
    {
        $now = now('UTC');
        $accountId = DB::table('client_accounts')->insertGetId([
            'name' => 'Old Group', 'contact_name' => 'Old Contact', 'contact_email' => 'old-contact@example.test',
            'contact_phone' => '07000000000', 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $businessId = DB::table('businesses')->insertGetId([
            'client_account_id' => $accountId, 'name' => 'Example Business', 'timezone' => 'Europe/London',
            'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('client_profiles')->insert([
            'business_id' => $businessId, 'contact_name' => 'Old Contact',
            'contact_email' => 'old-contact@example.test', 'contact_phone' => '07000000000',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $admin = User::create([
            'business_id' => $businessId, 'client_account_id' => $accountId, 'name' => 'Old Admin',
            'email' => 'old-admin@example.test', 'phone' => '07111111111',
            'password' => 'old-client-password-123', 'role' => 'admin', 'active' => true,
        ]);
        return [$accountId, $admin];
    }

    public function test_super_admin_can_edit_client_account_and_primary_login(): void
    {
        [$accountId] = $this->clientAccount();
        $platform = PlatformAdmin::create([
            'name' => 'Platform Operator', 'email' => 'operator@example.test',
            'password' => 'platform-password-123', 'active' => true,
        ]);
        $this->actingAs($platform, 'platform')->withSession(['platform_version' => $platform->auth_version]);
        Auth::shouldUse('web');

        $this->get('/platform/accounts/'.$accountId.'/edit')->assertOk()
            ->assertSee('Edit client account')->assertSee('Primary client administrator login');
        $this->post('/platform/accounts/'.$accountId.'/edit', [
            'client_name' => 'Updated Group', 'contact_name' => 'New Contact',
            'contact_email' => 'new-contact@example.test', 'contact_phone' => '07222222222',
            'admin_name' => 'New Admin', 'admin_email' => 'new-admin@example.test',
            'admin_phone' => '07333333333', 'password' => 'new-client-password-123',
            'password_confirmation' => 'new-client-password-123',
        ])->assertRedirect('/platform/accounts/'.$accountId)->assertSessionHas('status');

        $this->assertDatabaseHas('client_accounts', ['id' => $accountId, 'name' => 'Updated Group']);
        $this->assertDatabaseHas('users', ['email' => 'new-admin@example.test', 'name' => 'New Admin']);
        $this->assertTrue(Hash::check('new-client-password-123', User::where('email', 'new-admin@example.test')->firstOrFail()->password));
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'Client account details updated']);
    }

    public function test_client_admin_can_edit_account_details_and_change_password_with_current_password(): void
    {
        [$accountId, $admin] = $this->clientAccount();
        $this->actingAs($admin)->get('/account')->assertOk()->assertSee('Account settings');
        $this->post('/account', [
            'account_name' => 'Client Updated Group', 'contact_name' => 'Client Contact',
            'contact_email' => 'client-contact@example.test', 'contact_phone' => '07444444444',
            'admin_name' => 'Client Admin', 'admin_email' => 'client-admin@example.test',
            'admin_phone' => '07555555555', 'current_password' => 'old-client-password-123',
            'new_password' => 'replacement-client-password-123',
            'new_password_confirmation' => 'replacement-client-password-123',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('client_accounts', ['id' => $accountId, 'name' => 'Client Updated Group']);
        $this->assertDatabaseHas('client_profiles', ['business_id' => $admin->business_id, 'contact_email' => 'client-contact@example.test']);
        $updatedAdmin = $admin->fresh();
        $this->assertSame('client-admin@example.test', $updatedAdmin->email);
        $this->assertTrue(Hash::check('replacement-client-password-123', $updatedAdmin->password));
    }
}
