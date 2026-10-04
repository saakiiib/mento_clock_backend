<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database with everything needed to log in:
     * one business, one admin (web console) and one employee
     * assigned to a branch (mobile app).
     *
     * Safe to run anywhere: it only adds rows and stops if
     * either email already exists.
     *
     * One password for both accounts (12+ characters, no default):
     *   MENTO_SETUP_EMAIL      - admin email    (default: admin@example.test)
     *   MENTO_EMPLOYEE_EMAIL   - employee email (default: employee@example.test)
     *   MENTO_SETUP_PASSWORD   - password for both (required)
     */
    public function run(): void
    {
        $adminEmail = env('MENTO_SETUP_EMAIL', 'admin@example.test');
        $employeeEmail = env('MENTO_EMPLOYEE_EMAIL', 'employee@example.test');
        $password = env('MENTO_SETUP_PASSWORD');

        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException(
                'Set MENTO_SETUP_PASSWORD (12+ characters); no default password is provided.'
            );
        }

        DB::transaction(function () use ($adminEmail, $employeeEmail, $password) {
            if (User::where('email', $adminEmail)->exists()) {
                throw new \RuntimeException("Account {$adminEmail} already exists.");
            }
            if (User::where('email', $employeeEmail)->exists()) {
                throw new \RuntimeException("Account {$employeeEmail} already exists.");
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
                'email' => $adminEmail,
                'password' => $password,
                'role' => 'admin',
                'active' => true,
            ]);

            $branchId = DB::table('branches')->insertGetId([
                'business_id' => $businessId,
                'name' => 'Main Branch',
                'address' => 'Seeded branch - update the real address and GPS in the console',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'radius_m' => 100,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $employeeId = User::create([
                'business_id' => $businessId,
                'name' => 'Demo Employee',
                'email' => $employeeEmail,
                'password' => $password,
                'role' => 'employee',
                'active' => true,
            ])->id;

            DB::table('employee_branches')->insert([
                'business_id' => $businessId,
                'user_id' => $employeeId,
                'branch_id' => $branchId,
            ]);

            $this->command->info("Admin (console): {$adminEmail}");
            $this->command->info("Employee (app):  {$employeeEmail}");
        });
    }
}
