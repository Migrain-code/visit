<?php

namespace App\Policies;

use App\Models\TourDeparture;
use App\Models\User;

/**
 * Tur kaydı (sefer) ilkeleri.
 *
 * REHBER YALNIZ KENDİ SEFERİNİ GÖRÜR. Bu kural iki yerde birden uygulanır: burada
 * (tek kayıt erişimi) ve listeleme sorgusunda (scopeVisibleTo). Yalnız sorguyu
 * filtrelemek yetmez — kayıt kimliğini bilen biri doğrudan adrese gidebilir.
 */
class TourDeparturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->seesPassengers();
    }

    public function view(User $user, TourDeparture $departure): bool
    {
        if ($user->isGuide()) {
            return (int) $departure->guide_id === (int) $user->getKey();
        }

        return $user->seesPassengers();
    }

    public function create(User $user): bool
    {
        return $user->managesOperations();
    }

    public function update(User $user, TourDeparture $departure): bool
    {
        return $user->managesOperations();
    }

    /** Yolcu kaydı olan sefer silinemez; önce gruplar taşınmalı ya da silinmelidir. */
    public function delete(User $user, TourDeparture $departure): bool
    {
        return $user->managesOperations() && ! $departure->groups()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Araç atama ve grupları araçlara dağıtma. */
    public function allocate(User $user, TourDeparture $departure): bool
    {
        return $user->managesOperations();
    }

    /** Yolcu listesini (manifesto) görme/yazdırma. */
    public function manifest(User $user, TourDeparture $departure): bool
    {
        return $this->view($user, $departure);
    }
}
