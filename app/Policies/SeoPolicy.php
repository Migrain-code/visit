<?php

namespace App\Policies;

use App\Models\User;

/**
 * SEO ve gözlem ilkeleri: anahtar kelimeler, hedefler, iç linkler, AI kayıtları,
 * yönlendirmeler, 404 ve bot ziyaretleri.
 *
 * Yalnız süper yönetici değiştirir. İçerik editörü görebilir (raporlama için);
 * diğer roller hiç görmez.
 */
class SeoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isEditor();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
