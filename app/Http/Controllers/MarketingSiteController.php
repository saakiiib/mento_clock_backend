<?php

namespace App\Http\Controllers;

use App\Support\MarketingSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class MarketingSiteController
{
    public function index(Request $request)
    {
        $s = MarketingSite::settings();
        return $this->secure(response()->view('marketing.index', [
            's' => $s,
            'features' => MarketingSite::items('feature'),
            'plans' => MarketingSite::items('plan'),
            'faqs' => MarketingSite::items('faq'),
            'title' => $s['seo_title'] ?? 'MentoClock',
            'canonicalPath' => '/',
            'notice' => session('marketing_notice', ''),
        ]));
    }

    public function legal(Request $request, string $page)
    {
        abort_unless(in_array($page, ['privacy', 'terms'], true), 404);
        $s = MarketingSite::settings();
        $title = $page === 'privacy' ? 'Privacy notice | MentoClock' : 'Website terms | MentoClock';
        return $this->secure(response()->view('marketing.legal', [
            's' => $s, 'title' => $title, 'page' => $page, 'canonicalPath' => '/'.$page,
        ]));
    }

    public function contact(Request $request)
    {
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect('/#contact');
        }

        $key = 'marketing-contact:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return redirect('/#contact')->withErrors(['contact' => 'Too many enquiries from this connection. Please try later or contact us directly on WhatsApp.']);
        }
        RateLimiter::hit($key, 3600);

        $values = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'required|string|max:120',
            'email' => 'required|email|max:254',
            'phone' => 'nullable|string|max:30',
            'employees' => ['required', 'string', 'in:1–10 employees,11–30 employees,31–100 employees,100+ employees'],
            'branches' => ['required', 'string', 'in:1 location,2–5 locations,6+ locations'],
            'message' => 'required|string|max:1000',
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Please agree that Mento Software can use these details to respond to your enquiry.']);

        DB::table('marketing_site_enquiries')->insert([
            'name' => trim($values['name']),
            'company' => trim($values['company']),
            'email' => strtolower(trim($values['email'])),
            'phone' => trim($values['phone'] ?? ''),
            'employees' => $values['employees'],
            'branches' => $values['branches'],
            'message' => trim($values['message']),
            'status' => 'new',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $message = "Hi Mento Software, I’m interested in MentoClock.\n\n".
            "Name: {$values['name']}\nBusiness: {$values['company']}\nEmail: {$values['email']}".
            (!empty($values['phone']) ? "\nPhone: {$values['phone']}" : '').
            "\nTeam: {$values['employees']}\nWorkplaces: {$values['branches']}\n\n{$values['message']}";
        $settings = MarketingSite::settings();
        return redirect()->away(MarketingSite::whatsapp($settings, $message));
    }

    private function secure(\Illuminate\Http\Response $response): \Illuminate\Http\Response
    {
        return $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'",
        ]);
    }
}
