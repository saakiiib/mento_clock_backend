<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_site_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->longText('value');
            $table->timestamps();
        });

        Schema::create('marketing_site_content', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->index();
            $table->string('title', 150);
            $table->text('body');
            $table->json('extras');
            $table->integer('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['kind', 'active', 'position']);
        });

        Schema::create('marketing_site_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('company', 120);
            $table->string('email', 254)->index();
            $table->string('phone', 30)->default('');
            $table->string('employees', 50);
            $table->string('branches', 50);
            $table->text('message');
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
            $table->index('created_at');
        });

        $defaults = require database_path('seeders/MarketingSiteDefaults.php');
        $now = now('UTC');
        foreach ($defaults['settings'] as $key => $value) {
            DB::table('marketing_site_settings')->insert([
                'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ($defaults['content'] as $position => [$kind, $title, $body, $extras]) {
            DB::table('marketing_site_content')->insert([
                'kind' => $kind, 'title' => $title, 'body' => $body,
                'extras' => json_encode($extras, JSON_THROW_ON_ERROR),
                'position' => $position, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_site_enquiries');
        Schema::dropIfExists('marketing_site_content');
        Schema::dropIfExists('marketing_site_settings');
    }
};
