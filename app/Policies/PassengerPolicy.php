<?php

namespace App\Policies;

use App\Models\Passenger;
use App\Models\User;

/**
 * Yolcu listesi salt okunurdur: kayıt, düzenleme ve silme grubun içinden yapılır.
 */
class PassengerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers();
    }

    public function view(User $user, Passenger $passenger): bool
    {
        return $passenger->group !== null && $user->can('view', $passenger->group);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Passenger $passenger): bool
    {
        return false;
    }

    public function delete(User $user, Passenger $passenger): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
