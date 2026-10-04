<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('businesses',function(Blueprint $t){$t->id();$t->string('name');$t->string('timezone')->default('Europe/London');$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('branches',function(Blueprint $t){$t->id();$t->foreignId('business_id')->constrained()->restrictOnDelete();$t->string('name');$t->string('address');$t->decimal('latitude',10,7);$t->decimal('longitude',10,7);$t->unsignedInteger('radius_m')->default(100);$t->boolean('active')->default(true);$t->timestamps();$t->unique(['id','business_id']);});
  Schema::create('users',function(Blueprint $t){$t->id();$t->foreignId('business_id')->constrained()->restrictOnDelete();$t->string('name');$t->string('email')->unique();$t->string('password');$t->enum('role',['admin','employee'])->default('employee');$t->boolean('active')->default(true);$t->rememberToken();$t->timestamps();$t->unique(['id','business_id']);});
  Schema::create('employee_branches',function(Blueprint $t){$t->foreignId('business_id');$t->foreignId('user_id');$t->foreignId('branch_id');$t->primary(['user_id','branch_id']);$t->foreign(['user_id','business_id'])->references(['id','business_id'])->on('users')->cascadeOnDelete();$t->foreign(['branch_id','business_id'])->references(['id','business_id'])->on('branches')->cascadeOnDelete();});
  Schema::create('attendance_records',function(Blueprint $t){$t->id();$t->foreignId('business_id');$t->foreignId('user_id');$t->foreignId('branch_id');$t->timestamp('clock_in');$t->timestamp('clock_out')->nullable();$t->decimal('in_lat',10,7);$t->decimal('in_lng',10,7);$t->decimal('out_lat',10,7)->nullable();$t->decimal('out_lng',10,7)->nullable();$t->timestamps();$t->index(['business_id','user_id','clock_out']);$t->foreign(['user_id','business_id'])->references(['id','business_id'])->on('users')->restrictOnDelete();$t->foreign(['branch_id','business_id'])->references(['id','business_id'])->on('branches')->restrictOnDelete();});
  Schema::create('api_tokens',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('token_hash',64)->unique();$t->timestamp('expires_at');$t->timestamps();});
  Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('business_id')->constrained();$t->foreignId('actor_id')->constrained('users');$t->foreignId('attendance_id')->constrained('attendance_records');$t->string('action');$t->text('reason');$t->json('before');$t->json('after');$t->timestamp('created_at');});
  Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
 }
 public function down(): void {foreach(['sessions','audit_logs','api_tokens','attendance_records','employee_branches','users','branches','businesses'] as $table) Schema::dropIfExists($table);}
};
