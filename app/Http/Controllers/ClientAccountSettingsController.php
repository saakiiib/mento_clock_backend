<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientAccountSettingsController
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' && $user->client_account_id, 403, 'Account settings are available to client administrators on shared client accounts.');
        $account = DB::table('client_accounts')->where('id', $user->client_account_id)->firstOrFail();
        return view('client.account-settings', compact('account', 'user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'admin' && $user->client_account_id, 403, 'Account settings are available to client administrators on shared client accounts.');
        if (is_string($request->input('admin_email'))) $request->merge(['admin_email' => strtolower(trim($request->input('admin_email')))]);

        $values = $request->validate([
            'account_name' => 'required|string|max:160',
            'contact_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'admin_name' => 'required|string|max:100',
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id), Rule::unique('platform_admins', 'email')],
            'admin_phone' => 'nullable|string|max:40',
            'current_password' => 'required_with:new_password|nullable|string',
            'new_password' => ['nullable', 'string', 'min:12', 'max:72', 'confirmed'],
        ]);

        if (!empty($values['new_password']) && !Hash::check($values['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $accountId = (int) $user->client_account_id;
        $changedIdentity = false;
        DB::transaction(function () use ($values, $user, $accountId, $request, &$changedIdentity) {
            $account = DB::table('client_accounts')->where('id', $accountId)->lockForUpdate()->firstOrFail();
            $current = $user->newQuery()->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $businessIds = DB::table('businesses')->where('client_account_id', $accountId)->orderBy('id')->pluck('id');
            $now = now('UTC');

            DB::table('client_accounts')->where('id', $accountId)->update([
                'name' => trim($values['account_name']),
                'contact_name' => trim($values['contact_name']),
                'contact_email' => strtolower(trim($values['contact_email'])),
                'contact_phone' => trim($values['contact_phone'] ?? ''),
                'updated_at' => $now,
            ]);
            if ($businessIds->isNotEmpty()) {
                DB::table('client_profiles')->whereIn('business_id', $businessIds)->update([
                    'contact_name' => trim($values['contact_name']),
                    'contact_email' => strtolower(trim($values['contact_email'])),
                    'contact_phone' => trim($values['contact_phone'] ?? ''),
                    'updated_at' => $now,
                ]);
            }

            $identityChanged = $current->name !== trim($values['admin_name'])
                || strtolower($current->email) !== strtolower(trim($values['admin_email']))
                || trim((string) ($current->phone ?? '')) !== trim((string) ($values['admin_phone'] ?? ''));
            $passwordChanged = !empty($values['new_password']);
            $current->name = trim($values['admin_name']);
            $current->email = strtolower(trim($values['admin_email']));
            $current->phone = trim($values['admin_phone'] ?? '');
            if ($passwordChanged) $current->password = $values['new_password'];
            $current->save();
            $changedIdentity = $identityChanged || $passwordChanged;

            if ($changedIdentity) {
                $current->tokens()->delete();
                DB::table('sessions')->where('user_id', $current->id)->where('id', '!=', $request->session()->getId())->delete();
            }

            foreach ($businessIds as $businessId) {
                DB::table('client_organisation_events')->insert([
                    'business_id' => $businessId,
                    'actor_id' => $current->id,
                    'action' => 'client_account_updated',
                    'details' => json_encode(['account_fields' => ['name', 'contact_name', 'contact_email', 'contact_phone'], 'login_details_changed' => $changedIdentity, 'password_changed' => $passwordChanged], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
            }
        });

        return back()->with('status', $changedIdentity
            ? 'Account details saved. Other signed-in devices have been signed out; your current session remains active.'
            : 'Client account details saved. The updated contact details now appear across your businesses.');
    }
}
