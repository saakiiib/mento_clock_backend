<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('client_assistance_sessions', function(Blueprint $t){
   $t->id();$t->foreignId('business_id')->constrained()->restrictOnDelete();
   $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
   $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
   $t->foreignId('operator_id')->nullable()->constrained('platform_admins')->nullOnDelete();
   $t->string('status',20)->default('pending');$t->string('purpose',300);
   $t->timestamp('expires_at')->nullable();$t->timestamp('revoked_at')->nullable();$t->timestamps();
   $t->index(['business_id','status','expires_at']);
  });
  Schema::create('client_assistance_events',function(Blueprint $t){
   $t->id();$t->foreignId('session_id')->constrained('client_assistance_sessions')->restrictOnDelete();
   $t->foreignId('business_id')->constrained()->restrictOnDelete();
   $t->foreignId('actor_id')->constrained('platform_admins')->restrictOnDelete();
   $t->string('action',80);$t->string('subject_type',40)->nullable();$t->unsignedBigInteger('subject_id')->nullable();
   $t->json('before_json')->nullable();$t->json('after_json')->nullable();$t->string('reason',300);
   $t->timestamp('created_at');$t->index(['business_id','created_at']);
  });
  Schema::create('platform_support_operators',function(Blueprint $t){$t->foreignId('admin_id')->primary()->constrained('platform_admins')->cascadeOnDelete();$t->foreignId('assigned_by')->constrained('platform_admins')->restrictOnDelete();$t->timestamp('created_at');});
  Schema::create('client_assistance_notices',function(Blueprint $t){$t->id();$t->foreignId('business_id')->constrained()->restrictOnDelete();$t->string('subject',150);$t->text('body');$t->timestamp('created_at');$t->timestamp('read_at')->nullable();$t->index(['business_id','created_at']);});
 }
 public function down():void {Schema::dropIfExists('client_assistance_notices');Schema::dropIfExists('platform_support_operators');Schema::dropIfExists('client_assistance_events');Schema::dropIfExists('client_assistance_sessions');}
};
