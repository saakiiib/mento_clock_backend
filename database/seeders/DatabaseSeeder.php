<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a fresh database with the first business
     * and its administrator account.
     *
     * Credentials come from the environment (no defaults):
     *   MENTO_SETUP_EMAIL     - admin email (default: admin@example.test)
     *   MENTO_SETUP_PASSWORD  - admin password, 12+ characters (required)
     */
    public function run(): void
    {
        $this->ensureLocalEnvironment();

        $email = getenv('MENTO_SETUP_EMAIL') ?: 'admin@example.test';
        $password = getenv('MENTO_SETUP_PASSWORD');

        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException(
                'Set MENTO_SETUP_PASSWORD (12+ characters); no default password is provided.'
            );
        }

        DB::transaction(function () use ($email, $password) {
            if (User::where('email', $email)->exists()) {
                throw new \RuntimeException('Account already exists.');
            }

            $businessId = DB::table('businesses')->insertGetId([
                'name' => 'Sushi Bar',
                'timezone' => 'Europe/London',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            User::create([
                'business_id' => $businessId,
                'name' => 'Business Administrator',
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'active' => true,
            ]);
        });
    }

    /**
     * Refuse to seed anything but a local/test database,
     * so production data can never be touched by accident.
     */
    private function ensureLocalEnvironment(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Seed only a local/test environment.');
        }
    }
}
