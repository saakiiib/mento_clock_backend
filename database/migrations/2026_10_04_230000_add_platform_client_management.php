<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('auth_version')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->foreignId('business_id')->primary()->constrained()->restrictOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('website')->nullable();
            $table->string('plan_label', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 100);
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('client_profiles');
        Schema::dropIfExists('platform_admins');
    }
};
