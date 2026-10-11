<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 40)->nullable();
            $table->text('national_insurance_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->text('home_address_line_1')->nullable();
            $table->text('home_address_line_2')->nullable();
            $table->text('home_city')->nullable();
            $table->text('home_county')->nullable();
            $table->text('home_postcode')->nullable();
            $table->text('home_country')->nullable();
            $table->string('alternative_phone', 40)->nullable();

            // Encrypted Eloquent attributes need text columns for ciphertext expansion.
            $table->text('bank_name')->nullable();
            $table->text('sort_code')->nullable();
            $table->text('bank_account_number')->nullable();

            $table->string('emergency_contact_relationship', 80)->nullable();
            $table->string('emergency_contact_email', 255)->nullable();
            $table->text('emergency_contact_address')->nullable();

            $table->text('passport_number')->nullable();
            $table->string('passport_nationality', 100)->nullable();
            $table->string('passport_issued_by', 150)->nullable();
            $table->date('passport_issued_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->text('visa_brp_number')->nullable();
            $table->string('visa_nationality', 100)->nullable();
            $table->string('visa_issued_by', 150)->nullable();
            $table->date('visa_issued_date')->nullable();
            $table->date('visa_expiry_date')->nullable();
            $table->text('cos_number')->nullable();
            $table->date('cos_start_date')->nullable();
            $table->date('cos_end_date')->nullable();

            $table->string('department', 120)->nullable();
            $table->string('employment_type', 60)->nullable();
            $table->string('shift_name', 120)->nullable();
            $table->string('late_policy', 120)->nullable();
            $table->string('day_off_rule', 120)->nullable();
            $table->date('date_of_confirmation')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('job_location', 180)->nullable();
            $table->text('job_description')->nullable();
            $table->unsignedBigInteger('reporting_authority_id')->nullable()->index();
            $table->unsignedBigInteger('leave_sanction_authority_id')->nullable()->index();
        });

        DB::table('users')->select('id', 'name')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2, PREG_SPLIT_NO_EMPTY) ?: [];
                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? (string) $user->name,
                    'last_name' => $parts[1] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'gender', 'national_insurance_number',
                'date_of_birth', 'marital_status', 'nationality', 'home_address_line_1', 'home_address_line_2',
                'home_city', 'home_county', 'home_postcode', 'home_country', 'alternative_phone', 'bank_name',
                'sort_code', 'bank_account_number', 'emergency_contact_relationship',
                'emergency_contact_email', 'emergency_contact_address', 'passport_number',
                'passport_nationality', 'passport_issued_by', 'passport_issued_date',
                'passport_expiry_date', 'visa_brp_number', 'visa_nationality', 'visa_issued_by',
                'visa_issued_date', 'visa_expiry_date', 'cos_number', 'cos_start_date', 'cos_end_date',
                'department', 'employment_type', 'shift_name', 'late_policy', 'day_off_rule',
                'date_of_confirmation', 'contract_start_date', 'contract_end_date', 'job_location',
                'job_description', 'reporting_authority_id', 'leave_sanction_authority_id',
            ]);
        });
    }
};
