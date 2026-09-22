<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Varsayılan rol en DAR yetkili olandır: rolü unutulan bir hesap yolcu verisini göremesin.
            $table->string('role', 30)->default(UserRole::Rehber->value)->index()->after('email');

            // Personel kartı — web sitesinin ön yüzünde gösterilebilir.
            $table->string('title')->nullable()->after('role');          // ünvan
            $table->string('phone', 30)->nullable()->after('title');
            $table->string('whatsapp', 30)->nullable()->after('phone');
            $table->string('photo')->nullable()->after('whatsapp');
            $table->text('bio')->nullable()->after('photo');
            $table->boolean('is_active')->default(true)->index()->after('bio');
            $table->boolean('show_on_site')->default(false)->index()->after('is_active');
            $table->unsignedInteger('sort_order')->default(0)->after('show_on_site');
        });

        // Mevcut tek kullanıcı süper yönetici olur; aksi hâlde kimse panele giremez.
        DB::table('users')->orderBy('id')->limit(1)->update(['role' => UserRole::SuperAdmin->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'title', 'phone', 'whatsapp', 'photo', 'bio', 'is_active', 'show_on_site', 'sort_order']);
        });
    }
};
