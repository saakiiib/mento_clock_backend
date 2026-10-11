<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category', 40);
            $table->string('title', 160);
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->date('expiry_date')->nullable();
            $table->string('status', 24)->default('pending_review');
            $table->text('notes')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'user_id', 'category']);
            $table->index(['business_id', 'expiry_date', 'status']);
        });

        Schema::create('employee_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('course_name', 180);
            $table->string('provider', 180)->nullable();
            $table->date('completed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 24)->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'user_id', 'expires_on']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('company_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->longText('body');
            $table->string('audience', 20)->default('all');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->boolean('acknowledgement_required')->default(false);
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'publish_at']);
            $table->index(['business_id', 'audience', 'target_id']);
        });

        Schema::create('company_notice_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('notice_id')->constrained('company_notices')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['notice_id', 'user_id']);
            $table->index(['business_id', 'user_id', 'acknowledged_at'], 'notice_receipts_business_user_ack_idx');
        });

        Schema::create('hr_module_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action', 50);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'subject_type', 'subject_id']);
            $table->index(['business_id', 'created_at']);
        });

        DB::table('modules')->where('code', 'compliance_tracking')->update([
            'name' => 'Compliance & Training', 'description' => 'Private employee evidence, review status, expiry tracking and training records.',
            'available' => true, 'updated_at' => now('UTC'),
        ]);
        DB::table('modules')->where('code', 'company_notices')->update([
            'name' => 'Company Notices', 'description' => 'Audience-targeted employee announcements and acknowledgements.',
            'available' => true, 'updated_at' => now('UTC'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_module_events');
        Schema::dropIfExists('company_notice_receipts');
        Schema::dropIfExists('company_notices');
        Schema::dropIfExists('employee_training_records');
        Schema::dropIfExists('employee_documents');
        DB::table('modules')->where('code', 'compliance_tracking')->update([
            'name' => 'Compliance Tracking', 'description' => 'Secure compliance records, expiry reminders and evidence history.',
            'available' => false, 'updated_at' => now('UTC'),
        ]);
        DB::table('modules')->where('code', 'company_notices')->update(['available' => false, 'updated_at' => now('UTC')]);
    }
};
