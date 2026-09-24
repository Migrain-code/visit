<?php

namespace Tests\Concerns;

use App\Enums\Permission;
use App\Models\User;

/**
 * Testlerde personel hesabı üretir. Yetki kutulu sistem: rol yok, yetki listesi var.
 */
trait CreatesStaff
{
    private int $staffSerial = 0;

    /** Tohumlanan süper yönetici (admin@example.com). */
    protected function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param  array<int, Permission|string>  $permissions */
    protected function staff(array $permissions = [], array $attributes = []): User
    {
        $this->staffSerial++;

        return User::query()->create(array_merge([
            'name' => 'Personel '.$this->staffSerial,
            'email' => 'personel'.$this->staffSerial.'@ornek.test',
            'password' => 'parola1234',
            'is_super_admin' => false,
            'permissions' => array_map(fn ($p) => $p instanceof Permission ? $p->value : $p, $permissions),
            'is_active' => true,
        ], $attributes));
    }

    protected function superAdmin(array $attributes = []): User
    {
        return $this->staff([], array_merge(['is_super_admin' => true, 'name' => 'Süper Yönetici'], $attributes));
    }

    /** Sefer/araç/dağıtım yetkili operasyon sorumlusu. */
    protected function operations(array $attributes = []): User
    {
        return $this->staff([
            Permission::ToursManage, Permission::VehiclesManage, Permission::AllocationManage,
            Permission::GroupsCreate, Permission::GroupsManage, Permission::RequestsManage,
        ], $attributes);
    }

    /** Yalnız yolcu ekleyen kayıt personeli. */
    protected function registrar(array $attributes = []): User
    {
        return $this->staff([Permission::GroupsCreate, Permission::RequestsManage], $attributes);
    }

    /** Hiç yetkisi olmayan hesap: rehber. */
    protected function guide(array $attributes = []): User
    {
        return $this->staff([], $attributes);
    }
}
