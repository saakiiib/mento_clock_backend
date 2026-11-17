<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedInteger('monthly_price_pence')->nullable();
            $table->string('price_basis', 24)->default('per_business');
        });

        Schema::table('business_modules', function (Blueprint $table) {
            $table->string('entitlement_type', 20)->default('included');
        });

        Schema::create('module_pricing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('core_plan_price_pence')->nullable();
            $table->unsignedSmallInteger('included_branches')->default(1);
            $table->unsignedSmallInteger('included_employees')->default(5);
            $table->timestamps();
        });
        DB::table('module_pricing_settings')->insert([
            'id' => 1, 'core_plan_price_pence' => 1500, 'included_branches' => 1,
            'included_employees' => 5, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);

        Schema::create('client_module_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('desired_access', 20)->default('paid');
            $table->string('status', 20)->default('pending');
            $table->string('granted_type', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('request_note', 1000)->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'module_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_module_requests');
        Schema::dropIfExists('module_pricing_settings');
        Schema::table('business_modules', function (Blueprint $table) {
            $table->dropColumn('entitlement_type');
        });
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['monthly_price_pence', 'price_basis']);
        });
    }
};
