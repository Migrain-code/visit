<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament'in kontrolü config('app.env') üzerinden yapılıyor.
        config(['app.env' => 'production']);
    }

    private function user(UserRole $role, bool $active = true): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $role->value.'@canli.test',
            'password' => 'parola1234',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public static function roles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->label() => [$r]])->all();
    }

    public function test_user_model_implements_the_filament_contract(): void
    {
        $this->assertInstanceOf(FilamentUser::class, new User);
    }

    #[DataProvider('roles')]
    public function test_every_active_role_can_enter_the_panel_in_production(UserRole $role): void
    {
        $this->actingAs($this->user($role))->get('/admin')->assertOk();
    }

    #[DataProvider('roles')]
    public function test_inactive_accounts_are_locked_out_in_production(UserRole $role): void
    {
        $this->actingAs($this->user($role, active: false))->get('/admin')->assertForbidden();
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
        putenv('ADMIN_PASSWORD=Trakya-Montaj-2026!x');

        try {
            app(AdminUserSeeder::class)->run();
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertTrue(User::where('email', 'guclu@canli.test')->where('role', UserRole::SuperAdmin)->exists());
    }
}
