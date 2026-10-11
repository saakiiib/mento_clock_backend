<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganisationProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $key): User
    {
        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'Workspace '.$key,
            'timezone' => 'Europe/London',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('client_profiles')->insert([
            'business_id' => $businessId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return User::create([
            'business_id' => $businessId,
            'name' => 'Admin '.$key,
            'email' => $key.'@example.test',
            'password' => 'test-password-123',
            'role' => 'admin',
            'active' => true,
        ]);
    }

    public function test_client_admin_can_update_organisation_profile_and_hours(): void
    {
        $admin = $this->admin('org-a');
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $hours[$day] = ['closed' => $day === 'sunday' ? '1' : '0', 'opens_at' => '09:00', 'closes_at' => '17:00'];
        }

        $this->actingAs($admin)->post('/company/profile', [
            'organisation_name' => 'Example Foods Limited',
            'organisation_type' => 'limited_company',
            'registration_number' => '12345678',
            'organisation_email' => 'office@example.test',
            'authorised_email' => 'owner@example.test',
            'trading_name_changed' => '0',
            'working_hours' => $hours,
        ])->assertRedirect()->assertSessionHas('status', 'Organisation profile saved.');

        $profile = DB::table('client_profiles')->where('business_id', $admin->business_id)->first();
        $this->assertSame('Example Foods Limited', $profile->organisation_name);
        $this->assertSame('12345678', $profile->registration_number);
        $this->assertTrue(json_decode($profile->operating_hours, true)['sunday']['closed']);
        $this->assertDatabaseHas('client_organisation_events', [
            'business_id' => $admin->business_id,
            'actor_id' => $admin->id,
            'action' => 'profile_updated',
        ]);
        $this->actingAs($admin)->get('/company')->assertOk()
            ->assertSee('Organisation profile')
            ->assertSee('Organisation setup')
            ->assertSee('% complete');
    }

    public function test_organisation_documents_are_private_to_the_client_workspace(): void
    {
        Storage::fake('local');
        $adminA = $this->admin('org-b');
        $adminB = $this->admin('org-c');

        $this->actingAs($adminA)->post('/company/documents', [
            'category' => 'other',
            'document' => UploadedFile::fake()->image('business-record.png'),
        ])->assertRedirect()->assertSessionHas('status', 'Organisation document uploaded.');

        $document = DB::table('client_organisation_documents')->where('business_id', $adminA->business_id)->first();
        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->storage_path);
        $this->actingAs($adminA)->get('/company/documents/'.$document->id.'/download')->assertOk();
        $this->actingAs($adminB)->get('/company/documents/'.$document->id.'/download')->assertNotFound();
    }
}
