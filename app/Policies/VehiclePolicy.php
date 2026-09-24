<?php

namespace App\Policies;

use App\Models\User;

/**
 * Araç filosu: yolcu verisini gören herkes listeyi görür, "araç filosu" yetkisi yönetir.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers() || $user->managesVehicles();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->managesVehicles();
    }

    public function update(User $user): bool
    {
        return $user->managesVehicles();
    }

    public function delete(User $user): bool
    {
        return $user->managesVehicles();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesVehicles();
    }

    public function reorder(User $user): bool
    {
        return $user->managesVehicles();
    }
}
