<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable()->after('clock_out');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });
    }
};
