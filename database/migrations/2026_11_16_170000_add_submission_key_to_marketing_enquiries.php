<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_site_enquiries', function (Blueprint $table) {
            $table->char('submission_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_site_enquiries', function (Blueprint $table) {
            $table->dropUnique(['submission_key']);
            $table->dropColumn('submission_key');
        });
    }
};
