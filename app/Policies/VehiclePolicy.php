<?php

namespace App\Policies;

use App\Models\User;

/**
 * Araç filosu: operasyon yönetir, kayıt personeli yalnız görür.
 */
class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->registersGroups();
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
