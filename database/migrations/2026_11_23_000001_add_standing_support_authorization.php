<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('client_support_authorizations',function(Blueprint $t){
   $t->id();$t->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
   $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('granted_at')->nullable();
   $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('revoked_at')->nullable();$t->timestamps();
  });
  Schema::create('client_standing_support_events',function(Blueprint $t){
   $t->id();$t->foreignId('authorization_id')->constrained('client_support_authorizations')->restrictOnDelete();
   $t->foreignId('business_id')->constrained()->restrictOnDelete();
   $t->foreignId('actor_id')->constrained('platform_admins')->restrictOnDelete();
   $t->string('action',80);$t->string('subject_type',40)->nullable();$t->unsignedBigInteger('subject_id')->nullable();
   $t->json('before_json')->nullable();$t->json('after_json')->nullable();$t->string('reason',300);
   $t->timestamp('created_at');$t->index(['business_id','created_at']);
  });
  Schema::create('client_support_authorization_history',function(Blueprint $t){
   $t->id();$t->foreignId('business_id')->constrained()->restrictOnDelete();
   $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
   $t->string('action',10);$t->timestamp('created_at');
  });
 }
 public function down():void {Schema::dropIfExists('client_support_authorization_history');Schema::dropIfExists('client_standing_support_events');Schema::dropIfExists('client_support_authorizations');}
};
