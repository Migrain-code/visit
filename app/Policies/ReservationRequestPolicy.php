<?php

namespace App\Policies;

use App\Models\ReservationRequest;
use App\Models\User;

/**
 * İletişim talepleri: "talepler" yetkisi olan görür ve işler; silme süper yöneticide.
 */
class ReservationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managesRequests();
    }

    public function view(User $user, ReservationRequest $request): bool
    {
        return $user->managesRequests();
    }

    /** Talepler siteden gelir; panelden elle oluşturulmaz. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReservationRequest $request): bool
    {
        return $user->managesRequests();
    }

    public function delete(User $user, ReservationRequest $request): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Talebi bir personele atama. */
    public function assign(User $user, ReservationRequest $request): bool
    {
        return $user->assignsRequests();
    }
}
