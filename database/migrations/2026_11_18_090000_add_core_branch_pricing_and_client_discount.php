<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('module_pricing_settings', 'core_extra_branch_price_pence')) {
            Schema::table('module_pricing_settings', function (Blueprint $table) {
                $table->unsignedInteger('core_extra_branch_price_pence')->nullable()->default(1200);
            });
        }

        if (!Schema::hasColumn('client_profiles', 'core_discount_percent')) {
            Schema::table('client_profiles', function (Blueprint $table) {
                $table->unsignedTinyInteger('core_discount_percent')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_profiles', 'core_discount_percent')) {
            Schema::table('client_profiles', function (Blueprint $table) {
                $table->dropColumn('core_discount_percent');
            });
        }

        if (Schema::hasColumn('module_pricing_settings', 'core_extra_branch_price_pence')) {
            Schema::table('module_pricing_settings', function (Blueprint $table) {
                $table->dropColumn('core_extra_branch_price_pence');
            });
        }
    }
};
