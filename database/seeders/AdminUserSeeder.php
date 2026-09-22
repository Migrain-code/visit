<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /** Canlıda ASLA kullanılmaması gereken, herkesin tahmin edebileceği parolalar. */
    private const WEAK_PASSWORDS = ['', 'password', 'admin', '123456', '12345678'];

    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('ADMIN_PASSWORD', 'password');

        $exists = User::query()->where('email', $email)->exists();

        // Canlıda yönetici hesabı YENİ oluşturulacaksa zayıf parolayla oluşturulmaz.
        // Panel /admin adresinde herkese açık; "admin@example.com / password" ilk
        // denenen şeydir. Hesap zaten varsa parolasına dokunulmaz.
        if (! $exists && app()->isProduction() && in_array($password, self::WEAK_PASSWORDS, true)) {
            throw new RuntimeException(
                'Canlı ortamda yönetici hesabı zayıf parolayla oluşturulamaz. '
                .'.env dosyasına ADMIN_EMAIL ve güçlü bir ADMIN_PASSWORD yazıp seed komutunu tekrar çalıştırın. '
                // config:cache sonrası env() .env dosyasını okumaz; parola yazılı olsa da görünmez.
                .'Yazdıysanız ve yine bu hatayı alıyorsanız önce "php artisan config:clear" çalıştırın.'
            );
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Yönetici',
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        // Var olan kurulumlarda ilk hesap süper yönetici olmalı; aksi hâlde
        // rol sistemi devreye girdiğinde kimse ayarlara ve kullanıcılara erişemez.
        if ($admin->role !== UserRole::SuperAdmin && User::query()->where('role', UserRole::SuperAdmin)->doesntExist()) {
            $admin->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        }
    }
}
