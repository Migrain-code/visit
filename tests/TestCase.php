<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * TÜM testler tohumlanmış veritabanıyla koşar.
     *
     * Neden burada: RefreshDatabase bellek içi SQLite'ı yalnız BİR KEZ migrate eder ve
     * "--seed" bayrağını ilk koşan test sınıfından alır. Sınıfların bir kısmı tohumlu,
     * bir kısmı tohumsuz olursa sonuç TEST SIRASINA bağlı hale gelir: tek başına geçen
     * bir test, paket hâlinde koşunca düşer. Tek yerde sabitlemek bu belirsizliği kaldırır.
     */
    protected bool $seed = true;

    /**
     * Güvenlik: testler veritabanını sıfırladığı için yalnızca bellek içi SQLite üzerinde çalışmalıdır.
     * Uygulama açılır açılmaz (RefreshDatabase devreye girmeden önce) bağlantıyı doğrular; böylece
     * .env'deki gerçek (MySQL) veritabanının tabloları hiçbir koşulda silinmez.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Testler yalnızca bellek içi SQLite ile çalışır (şu an: {$connection} / {$database}). ".
                'phpunit.xml ayarlarını kontrol edin ve "php artisan config:clear" çalıştırın.'
            );
        }
    }

    /**
     * Aynı test içinde kullanıcı değiştirmek: panelin AuthenticateSession ara katmanı,
     * oturumdaki parola özeti yeni kullanıcıyla uyuşmayınca oturumu kapatıp girişe
     * yönlendirir (302). Kullanıcı değişince oturum sıfırlanır.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $current = $this->app['auth']->guard($guard)->user();

        if ($current && ! $current->is($user)) {
            $this->app['auth']->forgetGuards();
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }
}
