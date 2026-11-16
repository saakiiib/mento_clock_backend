<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_site_enquiries', function (Blueprint $table) {
            $table->string('request_type', 24)->default('enquiry')->after('branches')->index();
            $table->timestamp('consented_at')->nullable()->after('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_site_enquiries', function (Blueprint $table) {
            $table->dropIndex(['request_type']);
            $table->dropColumn(['request_type', 'consented_at']);
        });
    }
};
