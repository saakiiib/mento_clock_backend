<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leave_profiles', function (Blueprint $table) {
            $table->decimal('holiday_hours_per_day', 4, 2)->nullable()->after('working_days');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_profiles', function (Blueprint $table) {
            $table->dropColumn('holiday_hours_per_day');
        });
    }
};
