<?php

namespace App\Policies;

use App\Models\TourGroup;
use App\Models\User;

/**
 * Grup ve yolcu kayıtları — sistemdeki en hassas veri (ad, TC, telefon).
 *
 * "Yolcu ekleme" yetkisi olan kendi girdiği grubu düzenler ve siler; herkesinkini
 * ancak "tüm grupları yönetme" yetkisi olan değiştirir. Yetkisiz hesap (rehber)
 * yalnız kendi turunun gruplarını görür, değiştiremez.
 */
class TourGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TourGroup $group): bool
    {
        if ($user->seesPassengers()) {
            return true;
        }

        if ($user->registersGroups() && $this->owns($user, $group)) {
            return true;
        }

        return $group->departure !== null && $user->isGuideOf($group->departure);
    }

    public function create(User $user): bool
    {
        return $user->registersGroups();
    }

    public function update(User $user, TourGroup $group): bool
    {
        return $user->managesGroups() || ($user->registersGroups() && $this->owns($user, $group));
    }

    public function delete(User $user, TourGroup $group): bool
    {
        return $user->managesGroups() || ($user->registersGroups() && $this->owns($user, $group));
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesGroups();
    }

    /** Grubu elle bir araca taşıma / sabitleme. */
    public function move(User $user, TourGroup $group): bool
    {
        return $user->managesOperations();
    }

    private function owns(User $user, TourGroup $group): bool
    {
        return (int) $group->created_by === (int) $user->getKey();
    }
}
