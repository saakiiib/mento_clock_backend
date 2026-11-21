<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('attendance_attempt_events', function(Blueprint $t){
   $t->bigIncrements('id');$t->uuid('request_id')->unique();
   $t->unsignedBigInteger('business_id')->nullable()->index();$t->unsignedBigInteger('user_id')->nullable()->index();
   $t->unsignedBigInteger('branch_id')->nullable()->index();$t->unsignedBigInteger('attendance_id')->nullable()->index();
   $t->string('action',32);$t->string('outcome',32);$t->string('reason_code',80)->nullable();
   $t->unsignedSmallInteger('http_status');$t->unsignedInteger('duration_ms');
   $t->string('app_version',60)->nullable();$t->string('device_platform',30)->nullable();
   $t->decimal('gps_accuracy_m',8,2)->nullable();$t->boolean('location_supplied')->default(false);
   $t->json('details')->nullable();$t->timestamp('created_at')->useCurrent();
   $t->index(['created_at','action']);$t->index(['business_id','created_at']);$t->index(['outcome','created_at']);
  });
  Schema::create('attendance_incident_alerts',function(Blueprint $t){
   $t->bigIncrements('id');$t->unsignedBigInteger('business_id')->nullable()->index();$t->unsignedBigInteger('branch_id')->nullable()->index();
   $t->string('code',80);$t->string('severity',16)->default('warning');$t->string('message',255);
   $t->unsignedInteger('event_count')->default(0);$t->timestamp('window_started_at');
   $t->timestamp('resolved_at')->nullable();$t->timestamps();$t->index(['code','branch_id','created_at']);
  });
  Schema::create('employee_diagnostic_reports',function(Blueprint $t){
   $t->bigIncrements('id');$t->unsignedBigInteger('business_id')->index();$t->unsignedBigInteger('user_id')->index();
   $t->uuid('request_id');$t->string('issue_type',60);$t->string('app_version',60)->nullable();
   $t->string('platform',30)->nullable();$t->string('client_state',32)->nullable();
   $t->unsignedBigInteger('client_attendance_id')->nullable();$t->unsignedBigInteger('server_attendance_id')->nullable();
   $t->boolean('state_mismatch')->default(false);$t->text('description')->nullable();$t->timestamp('created_at')->useCurrent();
   $t->index(['business_id','created_at']);
  });
 }
 public function down(): void {Schema::dropIfExists('employee_diagnostic_reports');Schema::dropIfExists('attendance_incident_alerts');Schema::dropIfExists('attendance_attempt_events');}
};
