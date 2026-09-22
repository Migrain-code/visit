<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operasyon tabloları: araç filosu, tur kayıtları (seferler), sefere atanan araçlar,
 * gruplar ve yolcular.
 *
 * TEMEL KURAL — GRUP BÖLÜNMEZ. Araç ataması yolcuda değil GRUPTA tutulur
 * (tour_groups.departure_vehicle_id). Yolcunun kendi araç alanı yoktur; böylece bir
 * grubun iki araca dağılması veri modelinde MÜMKÜN DEĞİLDİR, yalnız kodda değil.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hazır araç listesi: 19, 24, 50 koltuklu... Sefere buradan araç seçilir.
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plate', 20)->nullable();
            $table->unsignedSmallInteger('seat_count');    // YOLCU koltuğu; şoför hariç
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Tur kaydı = belirli bir turun belirli bir tarihteki seferi.
        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->dateTime('starts_at')->index();
            $table->date('ends_on')->nullable();
            $table->string('meeting_point')->nullable();
            $table->decimal('price', 10, 2)->nullable();   // boşsa turun fiyatı geçerli
            $table->unsignedSmallInteger('quota')->nullable(); // boşsa araçların toplam koltuğu
            $table->foreignId('guide_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->boolean('is_public')->default(true);   // web sitesindeki takvimde görünsün mü
            $table->text('notes')->nullable();
            $table->timestamp('allocated_at')->nullable(); // son otomatik dağıtım
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /*
         * Sefere atanan araç. Koltuk sayısı ve şoför bilgisi filodan KOPYALANIR:
         * filodaki araç sonradan değişse ya da silinse bile geçmiş seferin yolcu
         * listesi olduğu gibi kalır. Aynı araç tipi bir sefere birden çok kez eklenebilir.
         */
        Schema::create('departure_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('plate', 20)->nullable();
            $table->unsignedSmallInteger('seat_count');
            $table->unsignedSmallInteger('reserved_seats')->default(0); // rehber/görevli için ayrılan
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tour_groups', function (Blueprint $table) {
            $table->id();
            // Sefer silinirse gruplar da sessizce silinmesin: yolcu kaydı olan sefer silinemez.
            $table->foreignId('tour_departure_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name')->nullable();
            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->string('contact_email')->nullable();
            $table->string('pickup_point')->nullable();
            $table->unsignedSmallInteger('passenger_count')->default(0); // yolcu satırlarından türetilir
            $table->string('status', 20)->default('confirmed')->index();

            // Araç ataması GRUP düzeyinde: grup asla bölünmez.
            $table->foreignId('departure_vehicle_id')->nullable()->constrained('departure_vehicles')->nullOnDelete();
            $table->boolean('is_pinned')->default(false);  // elle sabitlendi; otomatik dağıtım dokunmaz

            $table->decimal('total_price', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reservation_request_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tour_departure_id', 'status']);
        });

        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_group_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->boolean('is_foreign')->default(false); // yabancı uyruklu: TC yerine pasaport
            $table->string('tc_no', 11)->nullable()->index();
            $table->string('passport_no', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Web sitesinden gelen rezervasyon talepleri.
        Schema::create('reservation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tour_departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('people_count')->default(1);
            $table->date('preferred_date')->nullable();
            $table->text('message')->nullable();
            $table->boolean('kvkk_accepted')->default(false);
            $table->string('status', 30)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->text('assignment_note')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('source', 30)->default('form');
            $table->string('page_url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_requests');
        Schema::dropIfExists('passengers');
        Schema::dropIfExists('tour_groups');
        Schema::dropIfExists('departure_vehicles');
        Schema::dropIfExists('tour_departures');
        Schema::dropIfExists('vehicles');
    }
};
