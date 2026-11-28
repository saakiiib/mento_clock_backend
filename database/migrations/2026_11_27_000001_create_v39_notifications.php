<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('v39_messages',function(Blueprint $t){
   $t->id();$t->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
   $t->string('actor_guard',16);$t->unsignedBigInteger('actor_id');
   $t->string('title',160);$t->text('body');$t->string('action_type',32)->default('none');
   $t->boolean('requires_ack')->default(false);$t->string('target_type',20);
   $t->string('status',20)->default('draft');$t->timestamp('scheduled_at')->nullable();$t->timestamp('published_at')->nullable();
   $t->timestamps();$t->index(['business_id','status']);$t->index(['status','scheduled_at']);
  });
  Schema::create('v39_recipients',function(Blueprint $t){
   $t->id();$t->foreignId('message_id')->constrained('v39_messages')->cascadeOnDelete();
   $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
   $t->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
   $t->timestamp('published_at')->nullable();$t->timestamp('read_at')->nullable();$t->timestamp('acknowledged_at')->nullable();$t->timestamp('completed_at')->nullable();
   $t->string('push_status',20)->default('not_configured');$t->unsignedTinyInteger('push_attempts')->default(0);$t->timestamp('push_sent_at')->nullable();$t->string('push_error',160)->nullable();
   $t->timestamps();$t->unique(['message_id','user_id']);$t->index(['user_id','published_at']);$t->index(['business_id','message_id']);
  });
  Schema::create('v39_device_tokens',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();$t->string('token_hash',64)->unique();$t->text('token_encrypted');$t->string('platform',12);$t->timestamp('last_seen_at');$t->timestamps();
  });
  Schema::create('v39_feedback',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users')->restrictOnDelete();$t->foreignId('business_id')->constrained('businesses')->restrictOnDelete();$t->foreignId('recipient_id')->nullable()->constrained('v39_recipients')->nullOnDelete();$t->text('comment');$t->timestamps();$t->index(['business_id','created_at']);});
  Schema::create('v39_audit_events',function(Blueprint $t){
   $t->id();$t->foreignId('message_id')->nullable()->constrained('v39_messages')->nullOnDelete();$t->string('actor_guard',16);$t->unsignedBigInteger('actor_id');$t->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();$t->string('event',48);$t->json('details')->nullable();$t->timestamp('created_at');$t->index(['business_id','created_at']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('v39_audit_events');Schema::dropIfExists('v39_feedback');Schema::dropIfExists('v39_device_tokens');Schema::dropIfExists('v39_recipients');Schema::dropIfExists('v39_messages');
 }
};
