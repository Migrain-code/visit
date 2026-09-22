<?php

namespace App\Policies;

use App\Models\User;

/**
 * Site içeriği ilkeleri: bölgeler, blog, galeri, yorumlar, SSS, sayfalar.
 *
 * Süper yönetici ve içerik editörü yönetir. Operasyon sorumlusu görür ama değiştiremez.
 * Kayıt personeli ve rehber içeriğe hiç girmez — panelleri sade kalmalıdır.
 */
class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesCatalog();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->managesContent();
    }

    public function update(User $user): bool
    {
        return $user->managesContent();
    }

    public function delete(User $user): bool
    {
        return $user->managesContent();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesContent();
    }

    public function reorder(User $user): bool
    {
        return $user->managesContent();
    }
}
