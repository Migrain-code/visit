<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hedef sayfalar (ticari niyetli, kelime sahibi olabilecek sayfalar)
        Schema::create('seo_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500);
            $table->string('url_hash', 32)->unique();
            // service | province | district | page | custom
            $table->string('target_type', 30)->index();
            $table->string('content_type', 60)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
        });

        // Kelime havuzu + SAHİPLİK (spec §3.1)
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 255);
            $table->string('keyword_hash', 32)->unique();
            // informational | commercial | transactional | navigational
            $table->string('search_intent', 30)->nullable()->index();
            // COMMERCIAL_PRIMARY | COMMERCIAL_VARIANT | BLOG_PRIMARY | BLOG_SECONDARY | BLOG_SUPPORTING
            $table->string('keyword_type', 30)->default('BLOG_PRIMARY')->index();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->unsignedInteger('search_volume')->nullable();

            // SAHİPLİK: ikisi birden dolu OLAMAZ.
            $table->unsignedBigInteger('owner_blog_id')->nullable()->index();
            $table->foreignId('target_id')->nullable()->constrained('seo_targets')->nullOnDelete();

            // ACTIVE | UNASSIGNED | HOLD_NO_OWNER | HOLD_NO_KEYWORD | HOLD_CANNIBALIZATION
            // DİKKAT: bu, aşağıdaki status (aktif/pasif) ile AYRI bir alandır (spec §3.1).
            $table->string('assignment_status', 30)->default('UNASSIGNED')->index();
            $table->string('slot', 30)->nullable();

            $table->boolean('status')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        // İçerik başına deterministik skor (spec §3.2)
        Schema::create('seo_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 60);
            $table->unsignedBigInteger('content_id');
            $table->string('url', 500)->nullable();
            $table->unsignedTinyInteger('score')->default(0)->index();
            $table->json('scores')->nullable();   // {meta, content, technical, links}
            $table->json('issues')->nullable();   // SABİT metinler (spec §10.4)
            $table->timestamp('analyzed_at')->nullable();
            // PASS | NEUTRAL | FAIL | UNKNOWN
            $table->string('google_index_status', 30)->nullable()->index();
            $table->text('google_index_detail')->nullable();
            $table->timestamp('index_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_analyses');
        Schema::dropIfExists('seo_keywords');
        Schema::dropIfExists('seo_targets');
    }
};
