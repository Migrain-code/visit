<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500);
            // Uygulamanın ARAMA ANAHTARI. Model kayıt öncesi doldurur (spec §7.10).
            $table->string('from_hash', 32)->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            // Kolon uzunluğu bilerek geniş: kısa tutulursa canlıda INSERT düşer (spec §7.10).
            $table->string('source', 40)->default('manual')->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->string('path_hash', 32)->unique();
            $table->unsignedBigInteger('hits')->default(1);
            $table->string('last_referrer', 500)->nullable();
            $table->string('last_user_agent', 300)->nullable();
            $table->boolean('resolved')->default(false)->index();
            $table->string('suggested_path', 500)->nullable();
            $table->decimal('suggestion_score', 5, 4)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_crawler_visits', function (Blueprint $table) {
            $table->id();
            $table->string('bot', 60)->index();
            $table->string('path', 500);
            $table->string('key_hash', 32)->unique();
            $table->unsignedBigInteger('hits')->default(1);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_crawler_visits');
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
    }
};
