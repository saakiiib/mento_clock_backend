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
            ->assertSee('Clock in/out & attendance reports')
            ->assertSee('£15.00')
            ->assertSee('Leave Management')
            ->assertSee('£1.50')
            ->assertSee('per additional active employee/month')
            ->assertSee('trial-dialog');
    }

    public function test_trial_request_is_recorded_and_shows_a_direct_whatsapp_action(): void
    {
        $this->post('/trial-request', [
            'name' => 'Alex Owner',
            'company' => 'Riverside Cafe',
            'email' => 'ALEX@EXAMPLE.TEST',
            'phone' => '07700900123',
            'employees' => '11–30 employees',
            'branches' => '2–5 locations',
            'consent' => '1',
            'submission_key' => str_repeat('A', 64),
        ])->assertOk()
            ->assertSee('Your request is safely recorded')
            ->assertSee('Open WhatsApp and send request')
            ->assertSee('https://wa.me/447745975978');

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
            'submission_key' => str_repeat('B', 64),
        ])->assertRedirect('/#trial-request')
            ->assertSessionHasErrors(['consent'], 'trialRequest');

        $this->assertSame(0, DB::table('marketing_site_enquiries')->count());
    }

    public function test_repeated_submission_with_the_same_key_is_saved_once(): void
    {
        $payload = [
            'name' => 'Alex Owner',
            'company' => 'Riverside Cafe',
            'email' => 'alex@example.test',
            'employees' => '1–10 employees',
            'branches' => '1 location',
            'consent' => '1',
            'submission_key' => str_repeat('C', 64),
        ];

        $this->post('/trial-request', $payload)->assertOk();
        $this->post('/trial-request', $payload)->assertOk();

        $this->assertSame(
            1,
            DB::table('marketing_site_enquiries')
                ->where('request_type', 'private_trial')
                ->where('submission_key', str_repeat('C', 64))
                ->count()
        );
    }

    public function test_contact_form_submission_is_classified_as_a_general_enquiry(): void
    {
        $this->post('/contact', [
            'name' => 'Sam Business',
            'company' => 'Riverside Cafe',
            'email' => 'sam@example.test',
            'phone' => '07700900123',
            'employees' => '1–10 employees',
            'branches' => '1 location',
            'message' => 'Please tell me about MentoClock.',
            'consent' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('marketing_site_enquiries', [
            'email' => 'sam@example.test',
            'request_type' => 'enquiry',
            'status' => 'new',
        ]);
    }
}
