<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add only columns that do not already exist.
        $columns = [
            'organisation_name' => fn (Blueprint $t) => $t->string('organisation_name', 180)->nullable(),
            'organisation_type' => fn (Blueprint $t) => $t->string('organisation_type', 40)->nullable(),
            'registration_number' => fn (Blueprint $t) => $t->string('registration_number', 80)->nullable(),
            'registered_office' => fn (Blueprint $t) => $t->string('registered_office', 500)->nullable(),
            'company_type' => fn (Blueprint $t) => $t->string('company_type', 100)->nullable(),
            'organisation_email' => fn (Blueprint $t) => $t->string('organisation_email')->nullable(),
            'landline_number' => fn (Blueprint $t) => $t->string('landline_number', 40)->nullable(),
            'company_status' => fn (Blueprint $t) => $t->string('company_status', 80)->nullable(),
            'incorporation_date' => fn (Blueprint $t) => $t->date('incorporation_date')->nullable(),
            'sic_code' => fn (Blueprint $t) => $t->string('sic_code', 20)->nullable(),
            'nature_of_business' => fn (Blueprint $t) => $t->text('nature_of_business')->nullable(),
            'current_officer' => fn (Blueprint $t) => $t->string('current_officer', 180)->nullable(),
            'psc_name' => fn (Blueprint $t) => $t->string('psc_name', 180)->nullable(),
            'psc_dob' => fn (Blueprint $t) => $t->string('psc_dob', 7)->nullable(),
            'psc_nationality' => fn (Blueprint $t) => $t->string('psc_nationality', 100)->nullable(),
            'psc_residence' => fn (Blueprint $t) => $t->string('psc_residence', 100)->nullable(),
            'psc_id_verification_status' => fn (Blueprint $t) => $t->string('psc_id_verification_status', 100)->nullable(),
            'psc_nature_of_control' => fn (Blueprint $t) => $t->text('psc_nature_of_control')->nullable(),
            'first_accounts_made_up_to' => fn (Blueprint $t) => $t->date('first_accounts_made_up_to')->nullable(),
            'accounts_due_by' => fn (Blueprint $t) => $t->date('accounts_due_by')->nullable(),
            'next_accounts_due' => fn (Blueprint $t) => $t->date('next_accounts_due')->nullable(),
            'year_end_date' => fn (Blueprint $t) => $t->date('year_end_date')->nullable(),
            'last_confirmation_statement_date' => fn (Blueprint $t) => $t->date('last_confirmation_statement_date')->nullable(),
            'next_confirmation_statement_date' => fn (Blueprint $t) => $t->date('next_confirmation_statement_date')->nullable(),
            'trading_name' => fn (Blueprint $t) => $t->string('trading_name', 180)->nullable(),
            'trading_period' => fn (Blueprint $t) => $t->string('trading_period', 30)->nullable(),
            'business_sector' => fn (Blueprint $t) => $t->string('business_sector', 120)->nullable(),
            'trading_name_changed' => fn (Blueprint $t) => $t->boolean('trading_name_changed')->default(false),
            'trading_name_change_details' => fn (Blueprint $t) => $t->text('trading_name_change_details')->nullable(),
            'authorised_first_name' => fn (Blueprint $t) => $t->string('authorised_first_name', 100)->nullable(),
            'authorised_last_name' => fn (Blueprint $t) => $t->string('authorised_last_name', 100)->nullable(),
            'authorised_designation' => fn (Blueprint $t) => $t->string('authorised_designation', 120)->nullable(),
            'authorised_phone' => fn (Blueprint $t) => $t->string('authorised_phone', 40)->nullable(),
            'authorised_email' => fn (Blueprint $t) => $t->string('authorised_email')->nullable(),
            'key_contact_first_name' => fn (Blueprint $t) => $t->string('key_contact_first_name', 100)->nullable(),
            'key_contact_last_name' => fn (Blueprint $t) => $t->string('key_contact_last_name', 100)->nullable(),
            'key_contact_designation' => fn (Blueprint $t) => $t->string('key_contact_designation', 120)->nullable(),
            'key_contact_phone' => fn (Blueprint $t) => $t->string('key_contact_phone', 40)->nullable(),
            'key_contact_email' => fn (Blueprint $t) => $t->string('key_contact_email')->nullable(),
            'address_line_1' => fn (Blueprint $t) => $t->string('address_line_1', 255)->nullable(),
            'address_line_2' => fn (Blueprint $t) => $t->string('address_line_2', 255)->nullable(),
            'address_line_3' => fn (Blueprint $t) => $t->string('address_line_3', 255)->nullable(),
            'city_county' => fn (Blueprint $t) => $t->string('city_county', 180)->nullable(),
            'postcode' => fn (Blueprint $t) => $t->string('postcode', 30)->nullable(),
            'country' => fn (Blueprint $t) => $t->string('country', 100)->nullable(),
            'operating_hours' => fn (Blueprint $t) => $t->json('operating_hours')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('client_profiles', $name)) {
                Schema::table('client_profiles', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }

        // Backfill only missing values; preserve existing profile data.
        foreach (
            DB::table('client_profiles as p')
                ->join('businesses as b', 'b.id', '=', 'p.business_id')
                ->select(
                    'p.business_id',
                    'p.organisation_name',
                    'p.organisation_email',
                    'p.registered_office',
                    'p.contact_email',
                    'p.address',
                    'b.name'
                )->get() as $profile
        ) {
            $updates = [];

            if (empty($profile->organisation_name)) {
                $updates['organisation_name'] = $profile->name;
            }

            if (empty($profile->organisation_email)) {
                $updates['organisation_email'] = $profile->contact_email;
            }

            if (empty($profile->registered_office)) {
                $updates['registered_office'] = $profile->address;
            }

            if ($updates !== []) {
                DB::table('client_profiles')
                    ->where('business_id', $profile->business_id)
                    ->update($updates);
            }
        }

        if (!Schema::hasTable('client_organisation_documents')) {
            Schema::create('client_organisation_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 50);
                $table->string('original_name', 255);
                $table->string('storage_path', 500)->unique();
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes');
                $table->timestamps();
                $table->index(['business_id', 'category', 'created_at']);
            });
        }

        if (!Schema::hasTable('client_organisation_events')) {
            Schema::create('client_organisation_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 60);
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['business_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally preserve data. Do not drop columns or tables
        // that may have existed before this migration.
    }
};
