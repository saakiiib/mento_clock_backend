<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'mento:super-admin {--reset : Reset an existing super administrator}';
    protected $description = 'Create or recover a Mento Software platform administrator';

    public function handle(): int
    {
        $name = $this->ask('Your name');
        $email = strtolower(trim((string) $this->ask('Work email')));
        $password = $this->secret('Password (12–72 bytes)');
        $rules = ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'min:12', function ($attribute, $value, $fail) { if (strlen($value) > 72) $fail('Use at most 72 bytes for the password.'); }]];
        if (!$this->option('reset')) $rules['email'] .= '|unique:platform_admins,email';
        $validator = Validator::make(compact('name', 'email', 'password'), $rules);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) $this->error($error);
            return self::FAILURE;
        }
        if ($this->option('reset')) {
            $admin = PlatformAdmin::where('email', $email)->first();
            if (!$admin) { $this->error('No platform administrator has that email.'); return self::FAILURE; }
            $admin->forceFill(['name' => $name, 'password' => $password, 'active' => true, 'auth_version' => $admin->auth_version + 1])->save();
        } else {
            PlatformAdmin::create(['name' => $name, 'email' => $email, 'password' => $password, 'active' => true]);
        }
        $this->info('Super administrator ready. Sign in at /login to open the platform console.');
        return self::SUCCESS;
    }
}
