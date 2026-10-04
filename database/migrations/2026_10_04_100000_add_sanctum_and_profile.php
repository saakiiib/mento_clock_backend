<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->string('phone',40)->nullable();$t->string('employee_code',40)->nullable();});
  Schema::create('personal_access_tokens',function(Blueprint $t){$t->id();$t->morphs('tokenable');$t->string('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable()->index();$t->timestamps();});
  Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
 }
 public function down(): void {Schema::dropIfExists('password_reset_tokens');Schema::dropIfExists('personal_access_tokens');Schema::table('users',function(Blueprint $t){$t->dropColumn(['phone','employee_code']);});}
};
