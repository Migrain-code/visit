<?php

namespace App\Policies;

use App\Models\TourDeparture;
use App\Models\User;

/**
 * Tur ilkeleri.
 *
 * Tur listesini panele giren herkes görür (yolcu eklerken tur seçmek için).
 * Yolcu verisi ayrı korunur: yetkisiz hesap yalnız rehberi olduğu turu açabilir.
 * Bu kural iki yerde birden uygulanır: burada (tek kayıt) ve listeleme sorgusunda
 * (scopeVisibleTo). Kayıt kimliğini bilen biri doğrudan adrese gidebilir.
 */
class TourDeparturePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TourDeparture $departure): bool
    {
        return $user->seesPassengers() || $user->isGuideOf($departure);
    }

    public function create(User $user): bool
    {
        return $user->managesTours();
    }

    public function update(User $user, TourDeparture $departure): bool
    {
        return $user->managesTours();
    }

    /** Yolcu kaydı olan tur silinemez; önce gruplar taşınmalı ya da silinmelidir. */
    public function delete(User $user, TourDeparture $departure): bool
    {
        return $user->managesTours() && ! $departure->groups()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $user->managesTours();
    }

    /** Araç atama ve grupları araçlara dağıtma. */
    public function allocate(User $user, TourDeparture $departure): bool
    {
        return $user->managesOperations();
    }

    /** Komisyon yazma. */
    public function commission(User $user, TourDeparture $departure): bool
    {
        return $user->managesCommissions();
    }

    /** Kasa hareketi (ekstra gelir/gider) ekleme. */
    public function ledger(User $user, TourDeparture $departure): bool
    {
        return $user->viewsReports();
    }

    /** Yolcu listesini (manifesto) görme/yazdırma. */
    public function manifest(User $user, TourDeparture $departure): bool
    {
        return $this->view($user, $departure);
    }
}
