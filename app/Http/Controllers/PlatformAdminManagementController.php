<?php

namespace App\Http\Controllers;

use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformAdminManagementController
{
    public function index()
    {
        $admins = PlatformAdmin::query()->orderByDesc('active')->orderBy('name')->get();
        $currentAdminId = (int) Auth::guard('platform')->id();
        return view('platform.admins', compact('admins', 'currentAdminId'));
    }

    private function passwordRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable', 'string', 'min:12', 'confirmed',
            function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) $fail('Use at most 72 bytes for the password.');
            },
        ];
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) $request->merge(['email' => strtolower(trim($request->input('email')))]);
    }

    public function store(Request $request)
    {
        $this->normalizeEmail($request);
        $values = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', 'unique:platform_admins,email', 'unique:users,email'],
            'phone' => 'nullable|string|max:40',
            'password' => $this->passwordRules(true),
        ]);

        $admin = DB::transaction(function () use ($values) {
            $created = PlatformAdmin::create([
                'name' => trim($values['name']), 'email' => $values['email'],
                'phone' => trim($values['phone'] ?? ''), 'password' => $values['password'], 'active' => true,
            ]);
            $this->audit('Platform administrator created', [
                'target_admin_id' => $created->id, 'email' => $created->email,
            ]);
            return $created;
        });

        return back()->with('status', "Super Admin account for {$admin->name} created. Share the initial password securely.");
    }

    public function update(Request $request, int $adminId)
    {
        $this->normalizeEmail($request);
        $currentActorId = (int) Auth::guard('platform')->id();
        $admin = PlatformAdmin::findOrFail($adminId);
        $isSelf = $admin->id === $currentActorId;
        $values = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_admins', 'email')->ignore($admin->id), Rule::unique('users', 'email')],
            'phone' => 'nullable|string|max:40',
            'active' => 'required|boolean',
            'password' => $this->passwordRules(false),
        ]);
        if ($isSelf && !$values['active']) {
            throw ValidationException::withMessages(['active' => 'You cannot deactivate the Super Admin account you are currently using.']);
        }
        if ($isSelf && !empty($values['password'])) {
            throw ValidationException::withMessages(['password' => 'Use Account security to change your own password so your session can be updated safely.']);
        }

        DB::transaction(function () use ($admin, $values, $currentActorId) {
            $activeIds = DB::table('platform_admins')->where('active', true)->lockForUpdate()->pluck('id');
            $target = PlatformAdmin::where('id', $admin->id)->lockForUpdate()->firstOrFail();
            $active = (bool) $values['active'];
            if ($target->active && !$active && $activeIds->count() <= 1) {
                throw ValidationException::withMessages(['active' => 'Keep at least one active Super Admin account. Add another administrator before deactivating this one.']);
            }

            $changed = [];
            foreach (['name', 'email', 'phone'] as $field) {
                $value = trim((string) ($values[$field] ?? ''));
                if ($target->{$field} !== $value) $changed[] = $field;
            }
            if ((bool) $target->active !== $active) $changed[] = 'active';
            $passwordChanged = !empty($values['password']);
            if ($passwordChanged) $changed[] = 'password';

            $target->name = trim($values['name']);
            $target->email = $values['email'];
            $target->phone = trim($values['phone'] ?? '');
            $target->active = $active;
            if ($passwordChanged) $target->password = $values['password'];
            if ($passwordChanged || (bool) $admin->active !== $active) $target->auth_version++;
            $target->save();

            if ($changed !== []) {
                $this->audit('Platform administrator updated', [
                    'target_admin_id' => $target->id, 'changed_fields' => $changed,
                    'active' => $target->active, 'sessions_revoked' => $passwordChanged || (bool) $admin->active !== $active,
                ]);
            }
        });

        return back()->with('status', 'Super Admin account updated. Password or access changes revoke that administrator’s existing sessions.');
    }

    private function audit(string $action, array $details): void
    {
        DB::table('platform_audit_logs')->insert([
            'actor_id' => Auth::guard('platform')->id(), 'business_id' => null,
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
    }
}
