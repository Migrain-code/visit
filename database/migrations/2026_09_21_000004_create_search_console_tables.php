<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_search_queries', function (Blueprint $table) {
            $table->id();
            // dimension: query | page
            $table->string('dimension', 10)->index();
            $table->string('query', 500)->nullable();
            $table->string('path', 500)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 6)->default(0);
            $table->decimal('position', 8, 3)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            // Aynı dönemin tekrar çekilmesi satırları çoğaltmasın.
            $table->string('row_hash', 32)->unique();
            $table->timestamps();

            $table->index(['dimension', 'period_start']);
            $table->index(['dimension', 'clicks']);
        });

        Schema::create('seo_rank_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->date('data_date');
            $table->decimal('position_avg', 8, 3)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->string('ranking_url', 500)->nullable();
            // Beklenen sayfa mı sıralanıyor? false ise yanlış sayfa öne çıkıyor demektir.
            $table->boolean('target_match')->nullable();
            $table->timestamps();

            $table->unique(['keyword_id', 'data_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_rank_history');
        Schema::dropIfExists('seo_search_queries');
    }
};
