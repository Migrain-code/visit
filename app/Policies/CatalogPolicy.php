<?php

namespace App\Policies;

use App\Models\User;

/**
 * Tur kataloğu ilkeleri: turlar ve kategoriler.
 *
 * Fiyat ve program operasyonel bilgidir; bu yüzden içerik editörünün yanında
 * operasyon sorumlusu da yönetir. Kayıt personeli turları görür (müşteriye bilgi
 * verebilmek için) ama değiştiremez. Rehber kataloğa girmez.
 */
class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesCatalog() || $user->isRegistrar();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->managesCatalog();
    }

    public function update(User $user): bool
    {
        return $user->managesCatalog();
    }

    public function delete(User $user): bool
    {
        return $user->managesCatalog();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesCatalog();
    }

    public function reorder(User $user): bool
    {
        return $user->managesCatalog();
    }
}
