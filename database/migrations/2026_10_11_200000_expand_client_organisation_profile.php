<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('organisation_name', 180)->nullable();
            $table->string('organisation_type', 40)->nullable();
            $table->string('registration_number', 80)->nullable();
            $table->string('registered_office', 500)->nullable();
            $table->string('company_type', 100)->nullable();
            $table->string('organisation_email')->nullable();
            $table->string('landline_number', 40)->nullable();
            $table->string('company_status', 80)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('sic_code', 20)->nullable();
            $table->text('nature_of_business')->nullable();
            $table->string('current_officer', 180)->nullable();
            $table->string('psc_name', 180)->nullable();
            $table->string('psc_dob', 7)->nullable();
            $table->string('psc_nationality', 100)->nullable();
            $table->string('psc_residence', 100)->nullable();
            $table->string('psc_id_verification_status', 100)->nullable();
            $table->text('psc_nature_of_control')->nullable();
            $table->date('first_accounts_made_up_to')->nullable();
            $table->date('accounts_due_by')->nullable();
            $table->date('next_accounts_due')->nullable();
            $table->date('year_end_date')->nullable();
            $table->date('last_confirmation_statement_date')->nullable();
            $table->date('next_confirmation_statement_date')->nullable();
            $table->string('trading_name', 180)->nullable();
            $table->string('trading_period', 30)->nullable();
            $table->string('business_sector', 120)->nullable();
            $table->boolean('trading_name_changed')->default(false);
            $table->text('trading_name_change_details')->nullable();
            $table->string('authorised_first_name', 100)->nullable();
            $table->string('authorised_last_name', 100)->nullable();
            $table->string('authorised_designation', 120)->nullable();
            $table->string('authorised_phone', 40)->nullable();
            $table->string('authorised_email')->nullable();
            $table->string('key_contact_first_name', 100)->nullable();
            $table->string('key_contact_last_name', 100)->nullable();
            $table->string('key_contact_designation', 120)->nullable();
            $table->string('key_contact_phone', 40)->nullable();
            $table->string('key_contact_email')->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('address_line_3', 255)->nullable();
            $table->string('city_county', 180)->nullable();
            $table->string('postcode', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->json('operating_hours')->nullable();
        });

        foreach (DB::table('client_profiles as p')->join('businesses as b', 'b.id', '=', 'p.business_id')
            ->select('p.business_id', 'p.contact_email', 'p.address', 'b.name')->get() as $profile) {
            DB::table('client_profiles')->where('business_id', $profile->business_id)->update([
                'organisation_name' => $profile->name,
                'organisation_email' => $profile->contact_email,
                'registered_office' => $profile->address,
            ]);
        }

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

    public function down(): void
    {
        Schema::dropIfExists('client_organisation_events');
        Schema::dropIfExists('client_organisation_documents');
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'organisation_name', 'organisation_type', 'registration_number', 'registered_office', 'company_type',
                'organisation_email', 'landline_number', 'company_status', 'incorporation_date', 'sic_code',
                'nature_of_business', 'current_officer', 'psc_name', 'psc_dob', 'psc_nationality', 'psc_residence',
                'psc_id_verification_status', 'psc_nature_of_control', 'first_accounts_made_up_to', 'accounts_due_by',
                'next_accounts_due', 'year_end_date', 'last_confirmation_statement_date', 'next_confirmation_statement_date',
                'trading_name', 'trading_period', 'business_sector',
                'trading_name_changed', 'trading_name_change_details', 'authorised_first_name', 'authorised_last_name',
                'authorised_designation', 'authorised_phone', 'authorised_email', 'key_contact_first_name',
                'key_contact_last_name', 'key_contact_designation', 'key_contact_phone', 'key_contact_email',
                'address_line_1', 'address_line_2', 'address_line_3', 'city_county', 'postcode', 'country', 'operating_hours',
            ]);
        });
    }
};
