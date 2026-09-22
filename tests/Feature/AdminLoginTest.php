<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_seeded_admin_password_is_valid(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password', $admin->password), 'seed edilen yönetici parolası "password" olmalı');
    }

    public function test_seeded_admin_can_log_in_through_the_login_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@example.com', 'password' => 'yanlis-parola'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
