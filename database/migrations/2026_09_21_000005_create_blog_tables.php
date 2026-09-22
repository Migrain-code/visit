<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true)->index();
            // AI üretim hattı bu kategoriden konu üretsin mi?
            $table->boolean('auto_generate')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('primary_keyword')->nullable()->index();
            $table->json('faqs')->nullable();

            // 0 = taslak, 1 = yayında (spec §3.5: birleştirmede kaybeden status = 0 olur, SİLİNMEZ)
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->timestamp('publish_at')->nullable()->index();

            // manual | ai
            $table->string('source', 20)->default('manual')->index();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();

            // Çakışma birleştirmesinde hangi yazıya devredildi (spec §3.5)
            $table->foreignId('merged_into_id')->nullable()->constrained('blogs')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();

            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->index(['status', 'publish_at']);
        });

        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->foreign('owner_blog_id')->references('id')->on('blogs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->dropForeign(['owner_blog_id']);
        });

        Schema::dropIfExists('blogs');
        Schema::dropIfExists('blog_categories');
    }
};
