<?php

namespace App\Policies;

use App\Models\ReservationRequest;
use App\Models\User;

/**
 * Web sitesinden gelen rezervasyon talepleri: kayıt alabilen herkes görür ve işler.
 */
class ReservationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->registersGroups();
    }

    public function view(User $user, ReservationRequest $request): bool
    {
        return $user->registersGroups();
    }

    /** Talepler siteden gelir; panelden elle oluşturulmaz. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReservationRequest $request): bool
    {
        return $user->registersGroups();
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
