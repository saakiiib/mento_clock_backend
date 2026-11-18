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
            $table->unsignedInteger('included_price_pence')->nullable();
            $table->unsignedInteger('extra_employee_price_pence')->nullable();
            $table->string('pricing_model', 24)->default('tiered');
        });
        Schema::table('module_pricing_settings', function (Blueprint $table) {
            $table->unsignedInteger('core_extra_employee_price_pence')->nullable()->default(150);
        });

        // Preserve V30 catalogue values, converting the legacy per-employee rate
        // into a starting price for five people plus the same incremental rate.
        foreach (DB::table('modules')->get(['id', 'monthly_price_pence', 'price_basis']) as $module) {
            if ($module->monthly_price_pence === null) continue;
            $perEmployee = $module->price_basis === 'per_employee';
            DB::table('modules')->where('id', $module->id)->update([
                'included_price_pence' => $perEmployee ? $module->monthly_price_pence * 5 : $module->monthly_price_pence,
                'extra_employee_price_pence' => $perEmployee ? $module->monthly_price_pence : null,
                'pricing_model' => $perEmployee ? 'tiered' : 'per_business',
            ]);
        }

        // Introductory prices previously discussed. Only unset prices are filled;
        // any catalogue price already configured by Super Admin is retained.
        $rates = [
            'leave_management' => [150, 30, 'tiered'],
            'rota_shifts' => [300, 60, 'tiered'],
            'compliance_tracking' => [150, 30, 'tiered'],
            'company_notices' => [100, 10, 'tiered'],
            'holiday_calendar' => [100, 10, 'tiered'],
            'employee_lifecycle' => [100, 20, 'tiered'],
            'rota_advanced' => [100, 20, 'tiered'],
            'hr_reminders' => [100, 10, 'tiered'],
            'hr_reports' => [100, 20, 'tiered'],
            'role_permissions' => [100, null, 'per_business'],
            'document_management' => [100, 20, 'tiered'],
        ];
        foreach ($rates as $code => [$included, $extra, $model]) {
            DB::table('modules')->where('code', $code)->whereNull('included_price_pence')->update([
                'included_price_pence' => $included,
                'extra_employee_price_pence' => $extra,
                'pricing_model' => $model,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('module_pricing_settings', function (Blueprint $table) {
            $table->dropColumn('core_extra_employee_price_pence');
        });
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn(['included_price_pence', 'extra_employee_price_pence', 'pricing_model']);
        });
    }
};
