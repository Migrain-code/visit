<?php

namespace App\Policies;

use App\Models\TourGroup;
use App\Models\User;

/**
 * Grup ve yolcu kayıtları — sistemdeki en hassas veri (ad, TC, telefon).
 *
 * İçerik editörü hiç göremez. Rehber yalnız kendi seferinin gruplarını görür ve
 * değiştiremez. Kayıt personeli kayıt girer ve günceller; silmeyi yalnız KENDİ
 * girdiği kayıtta yapabilir.
 */
class TourGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers();
    }

    public function view(User $user, TourGroup $group): bool
    {
        if ($user->isGuide()) {
            return (int) $group->departure?->guide_id === (int) $user->getKey();
        }

        return $user->seesPassengers();
    }

    public function create(User $user): bool
    {
        return $user->registersGroups();
    }

    public function update(User $user, TourGroup $group): bool
    {
        return $user->registersGroups();
    }

    public function delete(User $user, TourGroup $group): bool
    {
        if ($user->managesOperations()) {
            return true;
        }

        return $user->isRegistrar() && (int) $group->created_by === (int) $user->getKey();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesOperations();
    }

    /** Grubu elle bir araca taşıma / sabitleme. */
    public function move(User $user, TourGroup $group): bool
    {
        return $user->managesOperations();
    }
}
