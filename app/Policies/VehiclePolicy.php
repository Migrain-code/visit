<?php

namespace App\Policies;

use App\Models\User;

/**
 * Araç filosu: araç/dağıtım/rapor yetkisi olan görür, "araç filosu" yetkisi yönetir.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesVehicles();
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
