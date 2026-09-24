<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sadeleştirme: "tur" artık tarihli tek bir kayıttır (tour_departures).
 *
 * Katalog (tours / tour_categories), bölgeler, blog, galeri ve SEO tabloları
 * SİLİNMEZ; yalnız kod bunları kullanmayı bırakır. Tur adı, görseli ve açıklaması
 * tarihli kayda taşınır. Eski katalog satırları varsa başlıkları kopyalanır.
 *
 * Ayrıca: yolcu biniş noktası, araç ücreti / rehberi, personel yetkileri,
 * tur komisyonları, kasa hareketleri ve iş başvuruları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->string('title')->nullable()->after('tour_id');
            $table->string('image')->nullable()->after('title');
            $table->string('image_alt')->nullable()->after('image');
            $table->string('badge', 40)->nullable()->after('image_alt');       // "Popüler", "Doğa & Tarih"
            $table->string('short_description', 300)->nullable()->after('badge');
            $table->text('description')->nullable()->after('short_description');
            $table->unsignedInteger('sort_order')->default(0)->after('is_public'); // ana sayfadaki sıra
        });

        // Eski katalogdan başlık ve görsel taşınır (yeni kurulumda tablo boştur).
        if (Schema::hasTable('tours')) {
            foreach (DB::table('tour_departures')->whereNotNull('tour_id')->get(['id', 'tour_id']) as $row) {
                $tour = DB::table('tours')->find($row->tour_id);

                if ($tour) {
                    DB::table('tour_departures')->where('id', $row->id)->update([
                        'title' => $tour->title,
                        'image' => $tour->image,
                        'image_alt' => $tour->image_alt,
                        'short_description' => $tour->short_description ? mb_substr($tour->short_description, 0, 300) : null,
                        'description' => $tour->description,
                        'sort_order' => $tour->sort_order ?? 0,
                    ]);
                }
            }
        }

        // Katalog bağı kalkar: sütun kalır (eski veri için), zorunluluğu ve kısıtı kalkar.
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropForeign(['tour_id']);
            $table->unsignedBigInteger('tour_id')->nullable()->change();
        });

        Schema::table('reservation_requests', function (Blueprint $table) {
            $table->dropForeign(['tour_id']);
            $table->dropForeign(['province_id']);
            $table->dropForeign(['district_id']);
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->string('pickup_point')->nullable()->after('phone');
        });

        Schema::table('departure_vehicles', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('driver_phone');          // araç ücreti
            $table->foreignId('guide_id')->nullable()->after('cost')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->index()->after('role');
            $table->json('permissions')->nullable()->after('is_super_admin');
        });

        // Eski roller yetki listesine çevrilir.
        $map = [
            'super_admin' => null,
            'operasyon' => ['tours.manage', 'vehicles.manage', 'allocation.manage', 'groups.create', 'groups.manage', 'requests.manage'],
            'kayit' => ['groups.create', 'requests.manage'],
            'icerik' => [],
            'rehber' => [],
        ];

        foreach (DB::table('users')->get(['id', 'role']) as $user) {
            $permissions = $map[$user->role] ?? [];

            DB::table('users')->where('id', $user->id)->update([
                'is_super_admin' => $user->role === 'super_admin',
                'permissions' => json_encode($permissions ?? []),
            ]);
        }

        Schema::create('tour_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tour_departure_id', 'user_id']);
        });

        Schema::create('tour_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_departure_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->index();            // income | expense
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('entry_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('position', 100)->nullable();
            $table->text('message')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->boolean('kvkk_accepted')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('tour_ledger_entries');
        Schema::dropIfExists('tour_commissions');

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_super_admin', 'permissions']));
        Schema::table('departure_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guide_id');
            $table->dropColumn('cost');
        });
        Schema::table('passengers', fn (Blueprint $table) => $table->dropColumn('pickup_point'));
        Schema::table('tour_departures', fn (Blueprint $table) => $table->dropColumn([
            'title', 'image', 'image_alt', 'badge', 'short_description', 'description', 'sort_order',
        ]));
    }
};
