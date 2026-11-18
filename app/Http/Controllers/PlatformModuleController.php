<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformModuleController
{
    public function guide()
    {
        $guides = config('module_guides', []);
        $modules = DB::table('modules')->orderBy('sort_order')->get()->map(function ($module) use ($guides) {
            $module->guide = $guides[$module->code] ?? [
                'purpose' => $module->description,
                'prepare' => ['Confirm the module is enabled for the demonstration business and use sample data.'],
                'demo' => ['Open the client workspace module and walk through its visible screens.'],
                'explain' => 'Describe the module using the current client workspace and confirm any business-specific limits before making a commitment.',
                'route' => 'Check the client workspace navigation for this module.',
            ];
            return $module;
        });
        return view('platform.module-guide', compact('modules'));
    }

    public function catalogue()
    {
        $modules = DB::table('modules')->orderBy('sort_order')->get();
        $corePlan = DB::table('module_pricing_settings')->where('id', 1)->first();
        return view('platform.module-catalogue', compact('modules', 'corePlan'));
    }

    public function updateCorePlan(Request $request)
    {
        $values = $request->validate([
            'monthly_price' => 'nullable|numeric|min:0|max:99999.99',
            'included_branches' => 'required|integer|min:1|max:500',
            'included_employees' => 'required|integer|min:1|max:500',
            'extra_employee_price' => 'nullable|numeric|min:0|max:99999.99',
        ]);
        $monthlyPrice = $values['monthly_price'] ?? null;
        DB::table('module_pricing_settings')->where('id', 1)->update([
            'core_plan_price_pence' => $monthlyPrice === null || $monthlyPrice === ''
                ? null : (int) round((float) $monthlyPrice * 100),
            'included_branches' => $values['included_branches'], 'included_employees' => $values['included_employees'],
            'core_extra_employee_price_pence' => ($values['extra_employee_price'] ?? '') === '' ? null : (int) round((float) $values['extra_employee_price'] * 100),
            'updated_at' => now('UTC'),
        ]);
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(), 'business_id' => null,
            'action' => 'Core plan pricing updated',
            'details' => json_encode(['monthly_price_pence' => $monthlyPrice === null || $monthlyPrice === '' ? null : (int) round((float) $monthlyPrice * 100), 'included_branches' => $values['included_branches'], 'included_employees' => $values['included_employees'], 'extra_employee_price_pence' => ($values['extra_employee_price'] ?? '') === '' ? null : (int) round((float) $values['extra_employee_price'] * 100)], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
        return back()->with('status', 'Core plan pricing and included capacity saved.');
    }

    public function updatePrice(Request $request, int $moduleId)
    {
        $module = DB::table('modules')->where('id', $moduleId)->firstOrFail();
        abort_if($module->is_core || $module->code === 'reports_analytics', 422, 'This module is included in the core plan. Update the core plan price instead.');
        $values = $request->validate([
            'monthly_price' => 'nullable|numeric|min:0|max:99999.99',
            'extra_employee_price' => 'nullable|numeric|min:0|max:99999.99',
            'pricing_model' => ['required', Rule::in(['tiered', 'per_business'])],
        ]);
        $monthlyPrice = $values['monthly_price'] ?? null;
        $pence = $monthlyPrice === null || $monthlyPrice === ''
            ? null : (int) round((float) $monthlyPrice * 100);
        $extraPence = $values['pricing_model'] === 'per_business' || ($values['extra_employee_price'] ?? '') === ''
            ? null : (int) round((float) $values['extra_employee_price'] * 100);
        DB::table('modules')->where('id', $moduleId)->update([
            'monthly_price_pence' => $values['pricing_model'] === 'tiered' ? $extraPence : $pence,
            'price_basis' => $values['pricing_model'] === 'tiered' ? 'per_employee' : 'per_business',
            'included_price_pence' => $pence, 'extra_employee_price_pence' => $extraPence,
            'pricing_model' => $values['pricing_model'], 'updated_at' => now('UTC'),
        ]);
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(), 'business_id' => null,
            'action' => 'Module pricing updated',
            'details' => json_encode(['module_id' => $moduleId, 'module' => $module->name, 'included_price_pence' => $pence, 'extra_employee_price_pence' => $extraPence, 'pricing_model' => $values['pricing_model']], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
        return back()->with('status', 'Module pricing saved. Clients will see the updated price on their next visit.');
    }

    public function requests()
    {
        $requests = DB::table('client_module_requests as r')
            ->join('businesses as b', 'b.id', '=', 'r.business_id')
            ->join('modules as m', 'm.id', '=', 'r.module_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.requested_by')
            ->leftJoin('platform_admins as pa', 'pa.id', '=', 'r.reviewed_by')
            ->leftJoin('client_accounts as ca', 'ca.id', '=', 'b.client_account_id')
            ->select('r.*', 'b.name as business_name', 'm.name as module_name', 'm.code as module_code',
                'm.monthly_price_pence', 'm.price_basis', 'm.included_price_pence', 'm.extra_employee_price_pence', 'm.pricing_model', DB::raw("COALESCE(u.name, 'Former user') as requester_name"), 'u.email as requester_email',
                'ca.name as account_name', 'pa.name as reviewer_name')
            ->orderByRaw("CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('r.created_at')->paginate(25)->withQueryString();
        return view('platform.module-requests', compact('requests'));
    }

    public function review(Request $request, int $requestId)
    {
        $values = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'granted_type' => ['nullable', Rule::in(['trial', 'paid'])],
            'trial_ends_at' => 'nullable|date|after:today',
            'paid_confirmed' => 'nullable|accepted',
            'review_note' => 'nullable|string|max:1000',
        ]);
        if ($values['decision'] === 'approved' && empty($values['granted_type'])) throw ValidationException::withMessages(['granted_type' => 'Choose whether access is a trial or paid entitlement.']);
        if ($values['decision'] === 'approved' && $values['granted_type'] === 'trial' && empty($values['trial_ends_at'])) throw ValidationException::withMessages(['trial_ends_at' => 'Set an end date for trial access.']);
        if ($values['decision'] === 'approved' && $values['granted_type'] === 'paid' && empty($values['paid_confirmed'])) throw ValidationException::withMessages(['paid_confirmed' => 'Confirm the agreed payment arrangement before activating paid access.']);

        DB::transaction(function () use ($values, $requestId) {
            $item = DB::table('client_module_requests')->where('id', $requestId)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'pending') throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            $now = now('UTC');
            if ($values['decision'] === 'approved') {
                $module = DB::table('modules')->where('id', $item->module_id)->where('available', true)->first();
                if (!$module || $module->is_core || $module->code === 'reports_analytics') throw ValidationException::withMessages(['decision' => 'This module is included in the core plan or is no longer available for activation.']);
                $end = $values['granted_type'] === 'trial' ? $values['trial_ends_at'] : null;
                $entitlement = ['assigned_by' => Auth::guard('platform')->id(), 'status' => 'active',
                    'entitlement_type' => $values['granted_type'], 'starts_at' => $now,
                    'ends_at' => $end ? \Illuminate\Support\Carbon::parse($end, 'Europe/London')->endOfDay()->utc() : null,
                    'updated_at' => $now];
                $existing = DB::table('business_modules')->where('business_id', $item->business_id)->where('module_id', $item->module_id)->exists();
                if ($existing) DB::table('business_modules')->where('business_id', $item->business_id)->where('module_id', $item->module_id)->update($entitlement);
                else DB::table('business_modules')->insert($entitlement + ['business_id' => $item->business_id, 'module_id' => $item->module_id, 'created_at' => $now]);
            }
            DB::table('client_module_requests')->where('id', $requestId)->update([
                'status' => $values['decision'], 'granted_type' => $values['decision'] === 'approved' ? $values['granted_type'] : null,
                'reviewed_by' => Auth::guard('platform')->id(), 'reviewed_at' => $now,
                'trial_ends_at' => $values['decision'] === 'approved' && $values['granted_type'] === 'trial' ? $values['trial_ends_at'] : null,
                'review_note' => $values['review_note'] ?? null, 'updated_at' => $now,
            ]);
            DB::table('platform_audit_logs')->insert([
                'actor_id' => Auth::guard('platform')->id(), 'business_id' => $item->business_id,
                'action' => 'Client module request '.$values['decision'],
                'details' => json_encode(['request_id' => $requestId, 'module_id' => $item->module_id,
                    'granted_type' => $values['decision'] === 'approved' ? $values['granted_type'] : null,
                    'trial_ends_at' => $values['decision'] === 'approved' ? ($values['trial_ends_at'] ?? null) : null], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        });
        return back()->with('status', $values['decision'] === 'approved'
            ? 'Request approved and module access activated for this business.'
            : 'Module request declined. The business access settings were not changed.');
    }
}
