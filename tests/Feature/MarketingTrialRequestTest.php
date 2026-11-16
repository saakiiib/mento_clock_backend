<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketingTrialRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_offers_a_private_trial_request(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Want to try MentoClock with your team?')
            ->assertSee('Request a private trial')
            ->assertSee('trial-dialog');
    }

    public function test_trial_request_is_recorded_and_handed_to_whatsapp(): void
    {
        $this->post('/trial-request', [
            'name' => 'Alex Owner',
            'company' => 'Riverside Cafe',
            'email' => 'ALEX@EXAMPLE.TEST',
            'phone' => '07700900123',
            'employees' => '11–30 employees',
            'branches' => '2–5 locations',
            'consent' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('marketing_site_enquiries', [
            'name' => 'Alex Owner',
            'company' => 'Riverside Cafe',
            'email' => 'alex@example.test',
            'request_type' => 'private_trial',
            'status' => 'new',
        ]);
    }

    public function test_trial_request_requires_privacy_consent(): void
    {
        $this->post('/trial-request', [
            'name' => 'Alex Owner',
            'company' => 'Riverside Cafe',
            'email' => 'alex@example.test',
            'employees' => '1–10 employees',
            'branches' => '1 location',
        ])->assertRedirect('/#trial-request')
            ->assertSessionHasErrors(['consent'], 'trialRequest');

        $this->assertSame(0, DB::table('marketing_site_enquiries')->count());
    }
}
