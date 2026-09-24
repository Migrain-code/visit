<?php

namespace App\Policies;

use App\Models\User;

/**
 * Personel yönetimi "personel ve yetki" yetkisine bağlıdır.
 *
 * Herkes KENDİ profilini düzenleyebilir (telefon, fotoğraf) — bu Filament'in
 * profil ekranından yapılır. Yetki ve süper yönetici alanları yalnız yöneticide.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->managesUsers() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->managesUsers();
    }

    public function update(User $user, User $model): bool
    {
        // Süper yöneticiyi yalnız süper yönetici düzenler: yetki yöneticisi kendini üstüne çıkaramaz.
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return $user->is($model);
        }

        return $user->managesUsers() || $user->is($model);
    }

    /** Kimse kendini silemez: panelde yönetici kalmama riski. */
    public function delete(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->isSuperAdmin()) {
            return $user->isSuperAdmin();
        }

        return $user->managesUsers();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
