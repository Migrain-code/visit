<?php

namespace App\Http\Controllers;

use App\Models\TourDeparture;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Yolcu listesi (manifesto): araç araç, yazdırılabilir.
 *
 * Yalnız panele giriş yapmış ve turu görme yetkisi olan kullanıcıya açılır.
 * Rehber yalnız kendi turunun listesini alabilir.
 */
class ManifestController extends Controller
{
    public function __invoke(TourDeparture $departure): View
    {
        // Bu adres panelin DIŞINDA: pasife alınan hesap paneli açamaz ama oturumu sürebilir.
        abort_unless((bool) auth()->user()?->is_active, 403);

        Gate::authorize('manifest', $departure);

        $departure->load('guide');

        $vehicles = $departure->vehicles()
            ->with(['guide', 'groups' => fn ($q) => $q->seatHolding()->with('passengers')])
            ->get();

        $waiting = $departure->seatHoldingGroups()
            ->whereNull('departure_vehicle_id')
            ->with('passengers')
            ->get();

        return view('admin.manifest', [
            'departure' => $departure,
            'vehicles' => $vehicles,
            'waiting' => $waiting,
        ]);
    }
}
