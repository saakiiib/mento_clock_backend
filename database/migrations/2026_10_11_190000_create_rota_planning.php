<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_holidays', function (Blueprint $table) {
            $table->boolean('is_closure')->default(false)->after('region');
        });
        Schema::create('rota_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120)->default('Shift');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'starts_at', 'status']);
            $table->index(['business_id', 'user_id', 'starts_at', 'ends_at']);
        });

        Schema::create('rota_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('rota_shift_id')->nullable()->constrained('rota_shifts')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
        });

        DB::table('modules')->where('code', 'rota_shifts')->update(['available' => true, 'updated_at' => now('UTC')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rota_events');
        Schema::dropIfExists('rota_shifts');
        Schema::table('business_holidays', function (Blueprint $table) {
            $table->dropColumn('is_closure');
        });
        DB::table('modules')->where('code', 'rota_shifts')->update(['available' => false, 'updated_at' => now('UTC')]);
    }
};
