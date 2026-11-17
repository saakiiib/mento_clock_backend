<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientModuleRequestController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only a business administrator can manage module requests.');
        $businessId = (int) ($request->attributes->get('active_business_id') ?: $request->user()->getRawOriginal('business_id'));
        $business = DB::table('businesses')->where('id', $businessId)->firstOrFail();
        $activeEmployees = DB::table('users')->where('business_id', $businessId)->where('role', 'employee')->where('active', true)->count();
        $activeBranches = DB::table('branches')->where('business_id', $businessId)->where('active', true)->count();
        $corePlan = DB::table('module_pricing_settings')->where('id', 1)->first();
        $modules = DB::table('modules')->orderBy('sort_order')->get();
        $pending = DB::table('client_module_requests')->where('business_id', $businessId)->where('status', 'pending')->pluck('module_id')->all();
        $latestRequestIds = DB::table('client_module_requests')->where('business_id', $businessId)->selectRaw('MAX(id)')->groupBy('module_id');
        $latestRequests = DB::table('client_module_requests')->where('business_id', $businessId)->whereIn('id', $latestRequestIds)
            ->select('module_id', 'status', 'desired_access', 'granted_type', 'trial_ends_at', 'review_note', 'created_at')
            ->get()->keyBy('module_id');
        $enabledIds = DB::table('business_modules as bm')->join('modules as m', 'm.id', '=', 'bm.module_id')
            ->where('bm.business_id', $businessId)->where('bm.status', 'active')->where('m.available', true)
            ->where(fn ($q) => $q->whereNull('bm.starts_at')->orWhere('bm.starts_at', '<=', now('UTC')))
            ->where(fn ($q) => $q->whereNull('bm.ends_at')->orWhere('bm.ends_at', '>', now('UTC')))
            ->pluck('m.id')->all();
        $entitlements = DB::table('business_modules')->where('business_id', $businessId)
            ->select('module_id', 'entitlement_type', 'ends_at')->get()->keyBy('module_id');
        return view('client.modules', compact('business', 'businessId', 'activeEmployees', 'activeBranches', 'corePlan', 'modules', 'pending', 'latestRequests', 'enabledIds', 'entitlements'));
    }

    public function store(Request $request, int $moduleId)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only a business administrator can request modules.');
        $businessId = (int) ($request->attributes->get('active_business_id') ?: $request->user()->getRawOriginal('business_id'));
        $values = $request->validate([
            'desired_access' => ['required', Rule::in(['trial', 'paid'])],
            'request_note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($values, $moduleId, $businessId, $request) {
            $module = DB::table('modules')->where('id', $moduleId)->where('available', true)->first();
            abort_unless($module && !$module->is_core && $module->code !== 'reports_analytics', 404, 'This module is included in the core plan or is not available to request.');
            $active = DB::table('business_modules')->where('business_id', $businessId)->where('module_id', $moduleId)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now('UTC')))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now('UTC')))->exists();
            if ($active) throw ValidationException::withMessages(['module' => 'This module is already active for the selected business.']);
            $pending = DB::table('client_module_requests')->where('business_id', $businessId)
                ->where('module_id', $moduleId)->where('status', 'pending')->lockForUpdate()->exists();
            if ($pending) throw ValidationException::withMessages(['module' => 'A request for this module is already awaiting review.']);
            DB::table('client_module_requests')->insert([
                'business_id' => $businessId, 'module_id' => $moduleId, 'requested_by' => $request->user()->id,
                'desired_access' => $values['desired_access'], 'status' => 'pending',
                'request_note' => $values['request_note'] ?? null, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        });

        return back()->with('status', 'Your module request has been sent to Mento Software. Access will start after Super Admin review.');
    }
}
