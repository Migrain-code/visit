<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

/**
 * Panel erişimi CANLI ortamda.
 *
 * Filament yerelde (APP_ENV=local) herkese kapıyı açar. Canlıda ise kullanıcı
 * modeli FilamentUser + canAccessPanel() uygulamıyorsa HERKES 403 alır — yerelde
 * her şey çalışırken hostinge çıkınca kimse panele giremez. Diğer testler yerel
 * ortamda koştuğu için bunu yakalayamaz; bu dosya ortamı canlıya çevirip dener.
 */
class ProductionPanelAccessTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament'in kontrolü config('app.env') üzerinden yapılıyor.
        config(['app.env' => 'production']);
    }

    public function test_user_model_implements_the_filament_contract(): void
    {
        $this->assertInstanceOf(FilamentUser::class, new User);
    }

    public function test_every_active_account_can_enter_the_panel_in_production(): void
    {
        foreach ([$this->superAdmin(), $this->operations(), $this->registrar(), $this->guide()] as $user) {
            $this->actingAs($user)->get('/admin')->assertOk();
        }
    }

    public function test_inactive_accounts_are_locked_out_in_production(): void
    {
        foreach ([$this->superAdmin(['is_active' => false]), $this->guide(['is_active' => false])] as $user) {
            $this->actingAs($user)->get('/admin')->assertForbidden();
        }
    }

    public function test_guests_are_sent_to_the_login_page_in_production(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_production_refuses_to_create_an_admin_with_the_default_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        putenv('ADMIN_EMAIL=yeni-yonetici@canli.test');
        putenv('ADMIN_PASSWORD=password');

        try {
            $this->expectException(\RuntimeException::class);
            app(AdminUserSeeder::class)->run();
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }
    }

    public function test_production_creates_an_admin_with_a_strong_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        putenv('ADMIN_EMAIL=guclu@canli.test');
        putenv('ADMIN_PASSWORD=Rize-Geziyor-2026!x');

        try {
            app(AdminUserSeeder::class)->run();
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertTrue(User::where('email', 'guclu@canli.test')->where('is_super_admin', true)->exists());
    }
}
