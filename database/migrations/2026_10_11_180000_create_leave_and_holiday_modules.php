<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('leave_year_start_month')->default(1);
            $table->unsignedTinyInteger('leave_year_start_day')->default(1);
            $table->decimal('default_annual_entitlement', 6, 2)->default(0);
            $table->string('holiday_region', 30)->default('england_wales');
            $table->timestamps();
        });

        Schema::create('employee_leave_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('annual_entitlement_override', 6, 2)->nullable();
            $table->json('working_days')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('business_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('name', 140);
            $table->date('holiday_date');
            $table->string('region', 30)->default('all');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'holiday_date', 'region']);
            $table->index(['business_id', 'holiday_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('leave_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('working_days', 6, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'start_date']);
            $table->index(['business_id', 'user_id', 'start_date', 'end_date']);
        });

        Schema::create('leave_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'leave_request_id', 'created_at'], 'leave_events_request_timeline_idx');
        });

        Schema::create('leave_admin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 60);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'subject_type', 'subject_id']);
        });

        DB::table('modules')->whereIn('code', ['leave_management', 'holiday_calendar'])->update([
            'available' => true,
            'updated_at' => now('UTC'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_admin_events');
        Schema::dropIfExists('leave_request_events');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('business_holidays');
        Schema::dropIfExists('employee_leave_profiles');
        Schema::dropIfExists('business_leave_policies');

        DB::table('modules')->whereIn('code', ['leave_management', 'holiday_calendar'])->update([
            'available' => false,
            'updated_at' => now('UTC'),
        ]);
    }
};
