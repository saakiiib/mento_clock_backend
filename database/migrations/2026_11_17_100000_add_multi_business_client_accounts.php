<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('client_account_id')->nullable()->after('id')->constrained('client_accounts')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_account_id')->nullable()->after('business_id')->constrained('client_accounts')->nullOnDelete();
        });

        // Existing one-business client accounts remain usable. Each is placed in
        // its own parent account until Super Admin deliberately groups them.
        foreach (DB::table('businesses as b')->leftJoin('client_profiles as p', 'p.business_id', '=', 'b.id')
            ->select('b.id', 'b.name', 'p.contact_name', 'p.contact_email', 'p.contact_phone')->orderBy('b.id')->get() as $business) {
            $accountId = DB::table('client_accounts')->insertGetId([
                'name' => $business->name,
                'contact_name' => $business->contact_name,
                'contact_email' => $business->contact_email,
                'contact_phone' => $business->contact_phone,
                'active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            DB::table('businesses')->where('id', $business->id)->update(['client_account_id' => $accountId]);
            DB::table('users')->where('business_id', $business->id)->where('role', 'admin')->update(['client_account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_account_id');
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_account_id');
        });
        Schema::dropIfExists('client_accounts');
    }
};
