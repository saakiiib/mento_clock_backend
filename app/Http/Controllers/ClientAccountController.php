<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkspacePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientAccountController
{
    public function index()
    {
        // Keep the list query free of GROUP BY so MySQL's default
        // ONLY_FULL_GROUP_BY mode works for both the page query and paginate count.
        $accounts = DB::table('client_accounts as c')->select('c.*')
            ->selectSub(DB::table('users as u')->selectRaw('COUNT(*)')
                ->whereColumn('u.client_account_id', 'c.id')->where('u.role', 'admin'), 'administrators_count')
            ->selectSub(DB::table('businesses')->selectRaw('COUNT(*)')->whereColumn('client_account_id', 'c.id'), 'businesses_count')
            ->selectSub(DB::table('businesses as b')->join('branches as w', 'w.business_id', '=', 'b.id')
                ->selectRaw('COUNT(*)')->whereColumn('b.client_account_id', 'c.id')->where('w.active', true), 'workspaces_count')
            ->orderByDesc('c.id')->paginate(20);
        return view('platform.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $modules = DB::table('modules')->where('available', true)->orderBy('sort_order')->get();
        return view('platform.accounts.create', compact('modules'));
    }

    public function store(Request $request)
    {
        if (is_string($request->input('admin_email'))) $request->merge(['admin_email' => strtolower(trim($request->input('admin_email')))]);
        $values = $request->validate([
            'client_name' => 'required|string|max:160', 'contact_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:255', 'contact_phone' => 'nullable|string|max:40',
            'admin_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:255|unique:users,email|unique:platform_admins,email',
            'password' => ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail) { if (strlen($value) > 72) $fail('Use at most 72 bytes for the password.'); }],
            'businesses' => 'required|array|min:1|max:20',
            'businesses.*.name' => 'required|string|max:100', 'businesses.*.timezone' => 'required|timezone',
            'businesses.*.workspace_limit' => 'required|integer|min:1|max:500',
            'businesses.*.employee_limit' => 'required|integer|min:1|max:500',
            'businesses.*.module_codes' => 'required|array|min:1',
            'businesses.*.module_codes.*' => [Rule::exists('modules', 'code')->where('available', true)],
        ]);

        $accountId = DB::transaction(function () use ($values) {
            $now = now('UTC');
            $accountId = DB::table('client_accounts')->insertGetId([
                'name' => $values['client_name'], 'contact_name' => $values['contact_name'],
                'contact_email' => $values['contact_email'], 'contact_phone' => $values['contact_phone'] ?? null,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $businessIds = [];
            foreach ($values['businesses'] as $businessValues) {
                $businessId = DB::table('businesses')->insertGetId([
                    'client_account_id' => $accountId, 'name' => $businessValues['name'],
                    'timezone' => $businessValues['timezone'], 'active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('client_profiles')->insert([
                    'business_id' => $businessId, 'contact_name' => $values['contact_name'],
                    'contact_email' => $values['contact_email'], 'contact_phone' => $values['contact_phone'] ?? null,
                    'branch_limit' => (int) $businessValues['workspace_limit'],
                    'employees_per_branch_limit' => (int) $businessValues['employee_limit'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                WorkspacePermissions::seedDefaultRoles($businessId);
                $this->syncModules($businessId, $businessValues['module_codes']);
                $businessIds[] = $businessId;
            }

            $admin = User::create([
                'business_id' => $businessIds[0], 'client_account_id' => $accountId,
                'name' => $values['admin_name'], 'email' => $values['admin_email'],
                'password' => $values['password'], 'role' => 'admin', 'active' => true,
            ]);
            foreach ($businessIds as $businessId) $this->audit($businessId, 'Client account and business created', ['client_account_id' => $accountId, 'administrator_id' => $admin->id]);
            return $accountId;
        });

        return redirect('/platform/accounts/'.$accountId)->with('status', 'Client account created with its businesses and workspace limits. Share the login securely.');
    }

    public function show(int $id)
    {
        $account = DB::table('client_accounts')->where('id', $id)->firstOrFail();
        $businesses = DB::table('businesses as b')->leftJoin('client_profiles as p', 'p.business_id', '=', 'b.id')
            ->where('b.client_account_id', $id)->select('b.*', 'p.branch_limit', 'p.employees_per_branch_limit')
            ->selectSub(DB::table('branches')->selectRaw('COUNT(*)')->whereColumn('business_id', 'b.id')->where('active', true), 'workspaces_count')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->whereColumn('business_id', 'b.id')->where('role', 'employee')->where('active', true), 'employees_count')
            ->orderBy('b.name')->get();
        $admins = User::where('client_account_id', $id)->where('role', 'admin')->get();
        $modules = DB::table('modules')->where('available', true)->orderBy('sort_order')->get();
        return view('platform.accounts.show', compact('account', 'businesses', 'admins', 'modules'));
    }

    /** Suspend/reactivate every business and user under one client login. */
    public function status(Request $request, int $id)
    {
        $values = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($values, $id) {
            $account = DB::table('client_accounts')->where('id', $id)->lockForUpdate()->firstOrFail();
            $businessIds = DB::table('businesses')->where('client_account_id', $id)->orderBy('id')->lockForUpdate()->pluck('id');
            $active = (bool) $values['active'];
            if (!$active && $businessIds->isNotEmpty()) {
                $openShifts = DB::table('attendance_records')->whereIn('business_id', $businessIds)->whereNull('clock_out')->count();
                abort_if($openShifts > 0, 422, 'This client has employees still clocked in. Resolve the open attendance records before suspending the client account.');
            }

            DB::table('client_accounts')->where('id', $id)->update(['active' => $active, 'updated_at' => now('UTC')]);
            $userIds = User::where(fn ($query) => $query->where('client_account_id', $id)
                ->orWhereIn('business_id', $businessIds))->pluck('id');
            if (!$active && $userIds->isNotEmpty()) {
                DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
                    ->whereIn('tokenable_id', $userIds)->delete();
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }
            foreach ($businessIds as $businessId) {
                $this->audit((int) $businessId, $active ? 'Client account activated' : 'Client account suspended', [
                    'client_account_id' => $id, 'client_name' => $account->name,
                    'all_businesses_affected' => true, 'records_preserved' => true,
                ]);
            }
        });

        return back()->with('status', $values['active']
            ? 'Client account activated. Any businesses suspended separately must still be activated individually.'
            : 'Client account suspended. All linked businesses are inaccessible, sessions were revoked and records were preserved.');
    }

    /** Toggle one business while leaving the other businesses under the client login available. */
    public function businessStatus(Request $request, int $accountId, int $businessId)
    {
        $values = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($values, $accountId, $businessId) {
            DB::table('client_accounts')->where('id', $accountId)->lockForUpdate()->firstOrFail();
            $business = DB::table('businesses')->where('client_account_id', $accountId)
                ->where('id', $businessId)->lockForUpdate()->firstOrFail();
            $active = (bool) $values['active'];
            if (!$active) {
                $openShifts = DB::table('attendance_records')->where('business_id', $businessId)->whereNull('clock_out')->count();
                abort_if($openShifts > 0, 422, 'This business has employees still clocked in. Resolve the open attendance records before deactivating it.');
            }
            DB::table('businesses')->where('id', $businessId)->update(['active' => $active, 'updated_at' => now('UTC')]);

            if (!$active) {
                $employeeIds = User::where('business_id', $businessId)->where('role', 'employee')->pluck('id');
                if ($employeeIds->isNotEmpty()) {
                    DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())
                        ->whereIn('tokenable_id', $employeeIds)->delete();
                    DB::table('sessions')->whereIn('user_id', $employeeIds)->delete();
                }
            }
            $this->audit($businessId, $active ? 'Business activated' : 'Business deactivated', [
                'client_account_id' => $accountId, 'business_name' => $business->name,
                'records_preserved' => true,
            ]);
        });

        return back()->with('status', $values['active']
            ? 'Business activated. Its client account must also be active for users to sign in.'
            : 'Business deactivated. Other businesses under this client account remain available; records were preserved.');
    }

    public function addBusiness(Request $request, int $id)
    {
        $account = DB::table('client_accounts')->where('id', $id)->firstOrFail();
        $values = $request->validate([
            'name' => 'required|string|max:100', 'timezone' => 'required|timezone',
            'workspace_limit' => 'required|integer|min:1|max:500', 'employee_limit' => 'required|integer|min:1|max:500',
            'module_codes' => 'required|array|min:1',
            'module_codes.*' => [Rule::exists('modules', 'code')->where('available', true)],
        ]);
        DB::transaction(function () use ($id, $values) {
            $businessId = DB::table('businesses')->insertGetId([
                'client_account_id' => $id, 'name' => $values['name'], 'timezone' => $values['timezone'],
                'active' => true, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $client = DB::table('client_accounts')->where('id', $id)->first();
            DB::table('client_profiles')->insert([
                'business_id' => $businessId, 'contact_name' => $client->contact_name,
                'contact_email' => $client->contact_email, 'contact_phone' => $client->contact_phone,
                'branch_limit' => $values['workspace_limit'], 'employees_per_branch_limit' => $values['employee_limit'],
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            WorkspacePermissions::seedDefaultRoles($businessId);
            $this->syncModules($businessId, $values['module_codes']);
            $this->audit($businessId, 'Business added to client account', ['client_account_id' => $id]);
        });
        return back()->with('status', 'Business added with its own workspace and employee limits.');
    }

    private function syncModules(int $businessId, array $requestedCodes): void
    {
        $codes = array_values(array_unique([...$requestedCodes, ...DB::table('modules')->where('is_core', true)->pluck('code')->all(), 'reports_analytics']));
        $actor = Auth::guard('platform')->id(); $now = now('UTC');
        foreach (DB::table('modules')->where('available', true)->get(['id', 'code']) as $module) {
            $key = ['business_id' => $businessId, 'module_id' => $module->id];
            $value = ['assigned_by' => $actor, 'status' => in_array($module->code, $codes, true) ? 'active' : 'inactive', 'updated_at' => $now];
            DB::table('business_modules')->updateOrInsert($key, [...$value, 'created_at' => $now]);
        }
    }

    private function audit(int $businessId, string $action, array $details): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(), 'business_id' => $businessId,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
    }
}
