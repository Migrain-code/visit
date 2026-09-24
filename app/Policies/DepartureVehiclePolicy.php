<?php

namespace App\Policies;

use App\Models\DepartureVehicle;
use App\Models\User;

/**
 * Tura atanan araçlar: yolcu verisini görebilen herkes görür, araç atama yetkisi yönetir.
 */
class DepartureVehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers();
    }

    public function view(User $user, ?DepartureVehicle $vehicle = null): bool
    {
        if ($user->seesPassengers()) {
            return true;
        }

        return $vehicle?->departure !== null && $user->isGuideOf($vehicle->departure);
    }

    public function create(User $user): bool
    {
        return $user->managesOperations();
    }

    public function update(User $user): bool
    {
        return $user->managesOperations();
    }

    public function delete(User $user): bool
    {
        return $user->managesOperations();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesOperations();
    }

    public function reorder(User $user): bool
    {
        return $user->managesOperations();
    }
}
