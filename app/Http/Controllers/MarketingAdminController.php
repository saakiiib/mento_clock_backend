<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;

class MarketingAdminController
{
    private const SETTINGS = [
        'site_name', 'hero_eyebrow', 'hero_title', 'hero_description', 'primary_cta',
        'features_title', 'features_description', 'workflow_title', 'pricing_title',
        'pricing_description', 'pricing_note', 'contact_title', 'contact_description',
        'whatsapp_number', 'contact_email', 'footer_text', 'site_url', 'portal_url',
        'app_store_url', 'play_store_url', 'seo_title', 'seo_description',
        'privacy_text', 'terms_text',
    ];

    public function index()
    {
        $settings = DB::table('marketing_site_settings')->pluck('value', 'key');
        $content = DB::table('marketing_site_content')->orderBy('kind')->orderBy('position')->orderBy('id')->get()->groupBy('kind');
        $enquiries = DB::table('marketing_site_enquiries')->orderByDesc('id')->limit(200)->get();
        $stats = [
            'new' => DB::table('marketing_site_enquiries')->where('status', 'new')->count(),
            'total' => DB::table('marketing_site_enquiries')->count(),
            'plans' => DB::table('marketing_site_content')->where('kind', 'plan')->where('active', true)->count(),
        ];
        return view('platform.website', compact('settings', 'content', 'enquiries', 'stats'));
    }

    public function updateSettings(Request $request)
    {
        $rules = [];
        foreach (self::SETTINGS as $key) $rules[$key] = 'nullable|string|max:12000';
        $rules['site_name'] = 'required|string|max:100';
        $rules['hero_title'] = 'required|string|max:500';
        $rules['hero_description'] = 'required|string|max:1000';
        $rules['contact_title'] = 'required|string|max:200';
        $rules['whatsapp_number'] = ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'];
        $rules['contact_email'] = 'required|email|max:254';
        foreach (['site_url', 'portal_url', 'app_store_url', 'play_store_url'] as $key) {
            $rules[$key] = 'nullable|url:https|max:255';
        }
        $values = $request->validate($rules);
        DB::transaction(function () use ($values) {
            foreach (self::SETTINGS as $key) {
                DB::table('marketing_site_settings')->where('key', $key)->update([
                    'value' => (string) ($values[$key] ?? ''), 'updated_at' => now('UTC'),
                ]);
            }
            $this->audit('Public website settings updated', ['fields' => self::SETTINGS]);
        });
        return back()->with('status', 'Public website content saved.');
    }

    public function saveContent(Request $request, ?int $id = null)
    {
        $values = $request->validate([
            'kind' => ['required', Rule::in(['plan', 'feature', 'faq'])],
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:3000',
            'position' => 'required|integer|between:-10000,10000',
            'active' => 'nullable|boolean',
            'monthly' => ['nullable', 'regex:/^$|^\d{1,6}(\.\d{1,2})?$/'],
            'annual' => ['nullable', 'regex:/^$|^\d{1,6}(\.\d{1,2})?$/'],
            'audience' => 'nullable|string|max:100',
            'features' => 'nullable|string|max:2500',
            'icon' => ['nullable', Rule::in(['clock', 'shop', 'pin', 'report', 'people', 'check'])],
            'featured' => 'nullable|boolean',
        ]);
        $kind = $values['kind'];
        $extras = [];
        if ($kind === 'plan') {
            $features = array_values(array_filter(array_map('trim', explode("\n", $values['features'] ?? ''))));
            validator(['features' => $features], ['features' => 'array|max:12', 'features.*' => 'string|max:200'])->validate();
            $extras = [
                'monthly' => $values['monthly'] ?? '',
                'annual' => $values['annual'] ?? '',
                'audience' => $values['audience'] ?? '',
                'features' => $features,
                'featured' => $request->boolean('featured'),
            ];
        } elseif ($kind === 'feature') {
            $extras = ['icon' => $values['icon'] ?? 'check'];
        }
        $payload = [
            'kind' => $kind, 'title' => trim($values['title']), 'body' => trim($values['body']),
            'extras' => json_encode($extras, JSON_THROW_ON_ERROR),
            'position' => (int) $values['position'], 'active' => $request->boolean('active'),
            'updated_at' => now('UTC'),
        ];
        if ($id !== null) {
            DB::table('marketing_site_content')->where('id', $id)->update($payload);
            $this->audit('Public website item updated', ['item_id' => $id, 'kind' => $kind]);
            return back()->with('status', 'Website item updated.');
        }
        $payload['created_at'] = now('UTC');
        $id = DB::table('marketing_site_content')->insertGetId($payload);
        $this->audit('Public website item added', ['item_id' => $id, 'kind' => $kind]);
        return back()->with('status', 'Website item added.');
    }

    public function deleteContent(int $id)
    {
        $kind = DB::table('marketing_site_content')->where('id', $id)->value('kind');
        DB::table('marketing_site_content')->where('id', $id)->delete();
        $this->audit('Public website item deleted', ['item_id' => $id, 'kind' => $kind]);
        return back()->with('status', 'Website item deleted.');
    }

    public function enquiry(Request $request, int $id)
    {
        $values = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'closed'])]]);
        DB::table('marketing_site_enquiries')->where('id', $id)->update(['status' => $values['status'], 'updated_at' => now('UTC')]);
        $this->audit('Website enquiry status updated', ['enquiry_id' => $id, 'status' => $values['status']]);
        return back()->with('status', 'Enquiry status saved.');
    }

    public function deleteEnquiry(int $id)
    {
        DB::table('marketing_site_enquiries')->where('id', $id)->delete();
        $this->audit('Website enquiry deleted', ['enquiry_id' => $id]);
        return back()->with('status', 'Enquiry deleted.');
    }

    private function audit(string $action, array $details): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(),
            'business_id' => null,
            'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
    }

    public function export()
    {
        $rows = DB::table('marketing_site_enquiries')->orderByDesc('id')->cursor();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Business', 'Email', 'Phone', 'Team size', 'Workplaces', 'Message', 'Status', 'Created at UTC']);
            foreach ($rows as $row) {
                $cells = [$row->id, $row->name, $row->company, $row->email, $row->phone, $row->employees, $row->branches, $row->message, $row->status, $row->created_at];
                fputcsv($out, array_map(static function ($value) {
                    $value = (string) $value;
                    return preg_match('/^[\s]*[=+\-@]/', $value) ? "'".$value : $value;
                }, $cells));
            }
            fclose($out);
        }, 'mentoclock-website-enquiries.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
