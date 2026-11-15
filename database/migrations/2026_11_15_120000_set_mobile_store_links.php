<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $links = [
            'app_store_url' => 'https://apps.apple.com/gb/app/mentoclock/id6818993573',
            'play_store_url' => 'https://play.google.com/store/apps/details?id=com.mentosoftware.mento_clock',
        ];

        foreach ($links as $key => $url) {
            DB::table('marketing_site_settings')
                ->where('key', $key)
                ->where(function ($query) {
                    $query->whereNull('value')->orWhere('value', '');
                })
                ->update(['value' => $url, 'updated_at' => now('UTC')]);
        }

        $defaults = [
            'hero_eyebrow' => 'Workforce time, rotas & holidays',
            'hero_title' => 'Clock in. Plan rotas. Manage holidays.',
            'hero_description' => 'A straightforward app for employees to clock in, check rotas and request leave — with clear branch-level tools for managers.',
            'primary_cta' => 'Download MentoClock',
        ];
        $previousDefaults = [
            'hero_eyebrow' => 'A clearer working day',
            'hero_title' => 'Good work.\nClearly recorded.',
            'hero_description' => 'Simple clock-in and clock-out for your people. Clear attendance reports for you. One calm view of every workplace.',
            'primary_cta' => 'Let’s talk about your team',
        ];
        foreach ($defaults as $key => $value) {
            DB::table('marketing_site_settings')
                ->where('key', $key)
                ->where('value', $previousDefaults[$key])
                ->update(['value' => $value, 'updated_at' => now('UTC')]);
        }
    }

    public function down(): void
    {
        // Keep live app-store links intact if this presentation update is rolled back.
    }
};
