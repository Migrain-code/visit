<?php

namespace App\Policies;

use App\Models\User;

/**
 * Kullanıcı yönetimi yalnız süper yöneticide.
 *
 * Herkes KENDİ profilini düzenleyebilir (telefon, fotoğraf) — bu Filament'in
 * profil ekranından yapılır, buradaki update yetkisiyle karıştırılmamalıdır.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || $user->is($model);
    }

    /** Kimse kendini silemez: panelde yönetici kalmama riski. */
    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
