<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class PlatformController
{
    private const PROFILE = ['contact_name', 'contact_email', 'contact_phone', 'address', 'website', 'plan_label', 'notes'];

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive']);
        $query = DB::table('businesses as b')->leftJoin('client_profiles as p', 'p.business_id', '=', 'b.id');
        if (!empty($filters['q'])) {
            $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
            $query->where(fn ($q) => $q->where('b.name', 'like', '%'.$needle.'%')->orWhere('p.contact_email', 'like', '%'.$needle.'%'));
        }
        if (!empty($filters['status'])) $query->where('b.active', $filters['status'] === 'active');
        $clients = $query->select('b.*', 'p.contact_name', 'p.contact_email', 'p.plan_label')
            ->selectSub(DB::table('users')->selectRaw('COUNT(*)')->whereColumn('business_id', 'b.id')->where('role', 'employee'), 'employees_count')
            ->selectSub(DB::table('branches')->selectRaw('COUNT(*)')->whereColumn('business_id', 'b.id'), 'branches_count')
            ->orderByDesc('b.id')->paginate(12)->withQueryString();
        $stats = [
            'clients' => DB::table('businesses')->count(),
            'active' => DB::table('businesses')->where('active', true)->count(),
            'people' => User::where('role', 'employee')->where('active', true)->count(),
            'workplaces' => DB::table('branches')->where('active', true)->count(),
        ];
        $activity = DB::table('platform_audit_logs as l')->join('platform_admins as a', 'a.id', '=', 'l.actor_id')
            ->leftJoin('businesses as b', 'b.id', '=', 'l.business_id')->select('l.*', 'a.name as actor', 'b.name as business')->orderByDesc('l.id')->limit(8)->get();
        return view('platform.index', compact('clients', 'stats', 'activity', 'filters'));
    }

    public function create()
    {
        return view('platform.create');
    }

    private function profileRules(): array
    {
        return ['name' => 'required|string|max:100', 'timezone' => 'required|timezone',
            'contact_name' => 'nullable|string|max:100', 'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:500',
            'website' => 'nullable|url:https|max:255', 'plan_label' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:3000'];
    }

    private function passwordRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:12', 'confirmed',
            function ($attribute, $value, $fail) { if (strlen($value) > 72) $fail('Use at most 72 bytes for the password.'); }];
    }

    private function normalizeEmail(Request $request, string $key): void
    {
        if (is_string($request->input($key))) $request->merge([$key => strtolower(trim($request->input($key)))]);
    }

    public function store(Request $request)
    {
        $this->normalizeEmail($request, 'admin_email');
        $values = $request->validate($this->profileRules() + [
            'admin_name' => 'required|string|max:100', 'admin_email' => 'required|email|max:255|unique:users,email|unique:platform_admins,email',
            'password' => $this->passwordRules(),
        ]);
        $id = DB::transaction(function () use ($values) {
            $id = DB::table('businesses')->insertGetId(['name' => $values['name'], 'timezone' => $values['timezone'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('client_profiles')->insert(['business_id' => $id, ...Arr::only($values, self::PROFILE), 'created_at' => now(), 'updated_at' => now()]);
            $admin = User::create(['business_id' => $id, 'name' => $values['admin_name'], 'email' => $values['admin_email'], 'password' => $values['password'], 'role' => 'admin', 'active' => true]);
            $this->audit($id, 'Client created', ['administrator_id' => $admin->id]);
            return $id;
        });
        return redirect('/platform/clients/'.$id)->with('status', 'Client created. Share the administrator login securely; no credentials have been emailed.');
    }

    public function show(int $id)
    {
        $business = DB::table('businesses')->where('id', $id)->firstOrFail();
        $profile = DB::table('client_profiles')->where('business_id', $id)->first();
        $admins = User::where('business_id', $id)->where('role', 'admin')->orderBy('name')->get();
        $stats = ['people' => User::where('business_id', $id)->where('role', 'employee')->count(),
            'workplaces' => DB::table('branches')->where('business_id', $id)->count(),
            'working' => DB::table('attendance_records')->where('business_id', $id)->whereNull('clock_out')->count(),
            'records' => DB::table('attendance_records')->where('business_id', $id)->count()];
        $activity = DB::table('platform_audit_logs as l')->join('platform_admins as a', 'a.id', '=', 'l.actor_id')
            ->where('l.business_id', $id)->select('l.*', 'a.name as actor')->orderByDesc('l.id')->limit(12)->get();
        return view('platform.show', compact('business', 'profile', 'admins', 'stats', 'activity'));
    }

    public function update(Request $request, int $id)
    {
        DB::table('businesses')->where('id', $id)->firstOrFail();
        $values = $request->validate($this->profileRules());
        DB::transaction(function () use ($values, $id) {
            DB::table('businesses')->where('id', $id)->update(['name' => $values['name'], 'timezone' => $values['timezone'], 'updated_at' => now()]);
            DB::table('client_profiles')->updateOrInsert(['business_id' => $id], [...Arr::only($values, self::PROFILE), 'updated_at' => now()]);
            $this->audit($id, 'Client profile updated', ['fields' => array_keys($values)]);
        });
        return back()->with('status', 'Client profile saved.');
    }

    public function status(Request $request, int $id)
    {
        $values = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($values, $id) {
            DB::table('businesses')->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('businesses')->where('id', $id)->update(['active' => $values['active'], 'updated_at' => now()]);
            if (!$values['active']) {
                $ids = User::where('business_id', $id)->pluck('id');
                PersonalAccessToken::where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $ids)->delete();
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
            }
            $this->audit($id, $values['active'] ? 'Client activated' : 'Client suspended', []);
        });
        return back()->with('status', $values['active'] ? 'Client access activated.' : 'Client access suspended. Attendance records are preserved.');
    }

    public function addAdmin(Request $request, int $id)
    {
        DB::table('businesses')->where('id', $id)->firstOrFail();
        $this->normalizeEmail($request, 'email');
        $values = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email|unique:platform_admins,email', 'password' => $this->passwordRules()]);
        DB::transaction(function () use ($values, $id) {
            $user = User::create(['business_id' => $id, 'name' => $values['name'], 'email' => $values['email'], 'password' => $values['password'], 'role' => 'admin', 'active' => true]);
            $this->audit($id, 'Client administrator added', ['administrator_id' => $user->id]);
        });
        return back()->with('status', 'Client administrator created. Share their login securely.');
    }

    public function updateAdmin(Request $request, int $id, int $userId)
    {
        $this->normalizeEmail($request, 'email');
        $user = User::where('business_id', $id)->where('role', 'admin')->findOrFail($userId);
        $values = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId), Rule::unique('platform_admins', 'email')],
            'active' => 'required|boolean', 'password' => $this->passwordRules(false)]);
        DB::transaction(function () use ($values, $user, $id) {
            DB::table('businesses')->where('id', $id)->lockForUpdate()->firstOrFail();
            $current = User::where('id', $user->id)->lockForUpdate()->firstOrFail();
            if (!$values['active'] && $current->active) {
                abort_if(User::where('business_id', $id)->where('role', 'admin')->where('active', true)->where('id', '!=', $user->id)->count() === 0, 422, 'Keep at least one active client administrator. Suspend the client instead if access should stop.');
            }
            $data = Arr::only($values, ['name', 'email', 'active']);
            if (!empty($values['password'])) $data['password'] = $values['password'];
            $current->update($data);
            $current->tokens()->delete();
            DB::table('sessions')->where('user_id', $current->id)->delete();
            $this->audit($id, 'Client administrator updated', ['administrator_id' => $current->id, 'password_changed' => !empty($values['password'])]);
        });
        return back()->with('status', 'Administrator updated. Their existing sessions were revoked.');
    }

    public function security()
    {
        return view('platform.security');
    }

    public function password(Request $request)
    {
        $values = $request->validate(['current_password' => 'required|string', 'password' => $this->passwordRules()]);
        $admin = Auth::guard('platform')->user();
        if (!Hash::check($values['current_password'], $admin->password)) return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        $admin->forceFill(['password' => $values['password'], 'auth_version' => $admin->auth_version + 1])->save();
        $request->session()->regenerate();
        $request->session()->put('platform_version', $admin->auth_version);
        $this->audit(null, 'Platform password changed', []);
        return back()->with('status', 'Password changed. Other platform sessions have been revoked.');
    }

    private function audit(?int $business, string $action, array $details): void
    {
        DB::table('platform_audit_logs')->insert(['actor_id' => Auth::guard('platform')->id(), 'business_id' => $business, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
