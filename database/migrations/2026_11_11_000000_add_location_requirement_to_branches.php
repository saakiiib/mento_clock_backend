<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('location_required')->default(false);
        });

        // Preserve the previous behaviour for existing workplaces: an address
        // meant GPS was required. New workplaces can choose this explicitly.
        DB::table('branches')->whereNotNull('address')->whereRaw("TRIM(address) <> ''")
            ->update(['location_required' => true]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('location_required');
        });
    }
};
