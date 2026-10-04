<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Edit these three lines, then run: php artisan db:seed --force
        $adminEmail = 'admin@example.test';
        $employeeEmail = 'employee@example.test';
        $password = 'Change-Me-12345';

        if (User::where('email', $adminEmail)->exists()) {
            $this->command->warn("Seed skipped: {$adminEmail} already exists.");
            return;
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
    }
}
