<?php

namespace App\Http\Controllers;

use App\Support\MarketingSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class MarketingSiteController
{
    public function index(Request $request)
    {
        $s = MarketingSite::settings();
        return $this->secure(response()->view('marketing.index', [
            's' => $s,
            'features' => MarketingSite::items('feature'),
            'plans' => MarketingSite::items('plan'),
            'pricingCorePlan' => DB::table('module_pricing_settings')->where('id', 1)->first(),
            'pricingModules' => DB::table('modules')->orderBy('sort_order')->get(),
            'faqs' => MarketingSite::items('faq'),
            'title' => $s['seo_title'] ?? 'MentoClock',
            'canonicalPath' => '/',
            'notice' => session('marketing_notice', ''),
            'trialSubmissionKey' => \Illuminate\Support\Str::random(64),
        ]));
    }

    public function legal(Request $request, string $page)
    {
        abort_unless(in_array($page, ['privacy', 'terms', 'cookies'], true), 404);
        $s = MarketingSite::settings();
        $titles = ['privacy' => 'Privacy notice', 'terms' => 'Website terms', 'cookies' => 'Cookie policy'];
        $title = $titles[$page].' | MentoClock';
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

    public function trialRequest(Request $request)
    {
        if (trim((string) $request->input('website', '')) !== '') {
            return redirect('/#trial-request');
        }

        $key = 'marketing-private-trial:'.hash('sha256', (string) $request->ip());
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'company' => 'required|string|max:120',
            'email' => 'required|email|max:254',
            'phone' => 'nullable|string|max:30',
            'employees' => ['required', 'string', 'in:1–10 employees,11–30 employees,31–100 employees,100+ employees'],
            'branches' => ['required', 'string', 'in:1 location,2–5 locations,6+ locations'],
            'consent' => 'accepted',
            'submission_key' => 'required|string|size:64|alpha_num',
        ], ['consent.accepted' => 'Please agree that Mento Software can use these details to arrange your private trial.']);

        if ($validator->fails()) {
            return redirect('/#trial-request')->withErrors($validator, 'trialRequest')->withInput();
        }

        $values = $validator->validated();
        $submissionKey = $values['submission_key'];
        $existing = DB::table('marketing_site_enquiries')
            ->where('request_type', 'private_trial')
            ->where('submission_key', $submissionKey)
            ->first();

        if (!$existing) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return redirect('/#trial-request')->withErrors([
                    'trial_request' => 'Too many trial requests from this connection. Please try again later or contact us on WhatsApp.',
                ])->withInput();
            }
            RateLimiter::hit($key, 3600);

            $name = trim($values['name']);
            $company = trim($values['company']);
            $email = strtolower(trim($values['email']));
            $phone = trim($values['phone'] ?? '');

            // Save first so the Super Admin still receives the request if WhatsApp is closed.
            try {
                DB::table('marketing_site_enquiries')->insert([
                    'name' => $name,
                    'company' => $company,
                    'email' => $email,
                    'phone' => $phone,
                    'employees' => $values['employees'],
                    'branches' => $values['branches'],
                    'request_type' => 'private_trial',
                    'submission_key' => $submissionKey,
                    'consented_at' => now('UTC'),
                    'message' => 'Private trial request submitted from the MentoClock website.',
                    'status' => 'new',
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            } catch (\Illuminate\Database\QueryException $exception) {
                // A simultaneous repeated POST can race the first insert; reuse its saved request.
                $existing = DB::table('marketing_site_enquiries')
                    ->where('request_type', 'private_trial')
                    ->where('submission_key', $submissionKey)
                    ->first();
                if (!$existing) {
                    throw $exception;
                }
            }
        }

        if ($existing) {
            $name = $existing->name;
            $company = $existing->company;
            $email = $existing->email;
            $phone = $existing->phone;
            $values['employees'] = $existing->employees;
            $values['branches'] = $existing->branches;
        } else {
            $name = trim($values['name']);
            $company = trim($values['company']);
            $email = strtolower(trim($values['email']));
            $phone = trim($values['phone'] ?? '');
        }

        $message = "Hi Mento Software, I’d like to request a private MentoClock trial.\n\n".
            "Owner: {$name}\nBusiness: {$company}\nEmail: {$email}".
            ($phone !== '' ? "\nPhone: {$phone}" : '').
            "\nTeam: {$values['employees']}\nWorkplaces: {$values['branches']}\n\n".
            'Please contact me to arrange a secure trial workspace.';

        // Save first, then show a same-site confirmation. WhatsApp is opened by
        // a direct user click on the confirmation page, which works more reliably
        // for app hand-off than an external redirect after a form POST.
        $whatsappUrl = MarketingSite::whatsapp(MarketingSite::settings(), $message);
        return $this->secure(response()->view('marketing.trial-success', [
            's' => MarketingSite::settings(),
            'title' => 'Trial request received | MentoClock',
            'canonicalPath' => '/',
            'firstName' => explode(' ', $name)[0] ?? $name,
            'whatsappUrl' => $whatsappUrl,
        ]));
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
