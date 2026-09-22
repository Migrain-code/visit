<?php

namespace App\Policies;

use App\Models\User;

/**
 * Sefere atanan araçlar: yolcu verisini görebilen herkes görür, operasyon yönetir.
 */
class DepartureVehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
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
