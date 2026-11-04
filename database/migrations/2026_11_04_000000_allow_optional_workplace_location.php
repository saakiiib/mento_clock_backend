<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A workplace without an address does not require a location at clock time.
        Schema::table('branches', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
        });

        // Store no coordinates rather than a misleading 0,0 when location is optional.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('in_lat', 10, 7)->nullable()->change();
            $table->decimal('in_lng', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('branches')->whereNull('address')->update(['address' => '']);
        DB::table('branches')->whereNull('latitude')->update(['latitude' => 0]);
        DB::table('branches')->whereNull('longitude')->update(['longitude' => 0]);
        DB::table('attendance_records')->whereNull('in_lat')->update(['in_lat' => 0]);
        DB::table('attendance_records')->whereNull('in_lng')->update(['in_lng' => 0]);

        Schema::table('branches', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
            $table->decimal('latitude', 10, 7)->nullable(false)->change();
            $table->decimal('longitude', 10, 7)->nullable(false)->change();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('in_lat', 10, 7)->nullable(false)->change();
            $table->decimal('in_lng', 10, 7)->nullable(false)->change();
        });
    }
};
