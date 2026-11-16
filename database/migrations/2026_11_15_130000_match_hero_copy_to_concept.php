<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'hero_eyebrow' => [
                'A clearer working day',
                'Workforce time, rotas & holidays',
            ],
            'hero_title' => [
                'Clock in. Plan rotas. Manage holidays.',
                'Clock in, plan rotas and manage holidays — all in one app.',
            ],
            'hero_description' => [
                'A straightforward app for employees to clock in, check rotas and request leave — with clear branch-level tools for managers.',
                'The simple workforce app for UK businesses. Track time, build rotas and manage leave, from anywhere.',
            ],
            'primary_cta' => [
                'Download MentoClock',
                'Download the app',
            ],
        ];

        foreach ($updates as $key => [$oldDefault, $newDefault]) {
            DB::table('marketing_site_settings')
                ->where('key', $key)
                ->where('value', $oldDefault)
                ->update(['value' => $newDefault, 'updated_at' => now('UTC')]);
        }
    }

    public function down(): void
    {
        // Preserve the current marketing copy when rolling back.
    }
};
