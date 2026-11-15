<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now('UTC');
        DB::table('marketing_site_settings')->updateOrInsert(
            ['key' => 'cookie_text'],
            [
                'value' => "This Cookie Policy explains how MentoClock uses cookies on this website and web portal.\n\nEssential session cookies: We use strictly necessary cookies to keep a secure sign-in session, protect forms and support essential website functions. The cookie name and expiry are controlled by the server configuration. Disabling these cookies in your browser may stop sign-in or protected forms from working.\n\nAnalytics and advertising: The current website source does not include analytics cookies, advertising pixels or social-media tracking cookies. If optional tracking technologies are introduced, this policy and the relevant choices will be updated before they are used.\n\nExternal services: Links to WhatsApp, Apple App Store and Google Play open services operated by other organisations. Their cookie and privacy practices apply after you leave this website.\n\nYou can clear or block cookies in your browser settings. Contact info@mentosoftware.co.uk with questions about this policy.",
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('marketing_site_settings')->where('key', 'cookie_text')->delete();
    }
};
