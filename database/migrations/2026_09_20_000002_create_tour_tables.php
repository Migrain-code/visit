<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tur kataloğu: kategoriler ve turlar.
 *
 * Bunlar web sitesinde YAYINLANAN içeriktir. Belirli bir tarihte yapılan sefer
 * (tour_departures) ve o sefere kayıtlı gruplar ayrı tablolardadır; aynı tur yılda
 * onlarca kez yapılır ve her seferin kendi tarihi, fiyatı, aracı ve yolcusu vardır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->json('faqs')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->json('gallery')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // Süre: günübirlik turda gece 0, gün 1'dir.
            $table->unsignedTinyInteger('duration_days')->default(1);
            $table->unsignedTinyInteger('duration_nights')->default(0);

            // Kişi başı fiyat. Sefer kendi fiyatını verebilir; vermezse bu kullanılır.
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->string('price_note')->nullable();

            $table->string('departure_point')->nullable();
            $table->string('destinations')->nullable();   // "Göreme, Uçhisar, Avanos"
            $table->string('transport')->nullable();       // "Lüks otobüs"
            $table->string('accommodation')->nullable();   // "4* otel, yarım pansiyon"

            $table->json('highlights')->nullable();
            $table->json('included')->nullable();
            $table->json('excluded')->nullable();
            $table->json('itinerary')->nullable();         // [{title, description}] gün gün program
            $table->json('faqs')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_featured')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
        Schema::dropIfExists('tour_categories');
    }
};
