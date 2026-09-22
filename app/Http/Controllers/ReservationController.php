<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationRequestReceived;
use App\Models\ReservationRequest;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Services\Security\Recaptcha;
use App\Support\SchemaOrg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function create(Request $request): View
    {
        // Tur sayfasındaki "Rezervasyon yap" bağlantısı turu ve tarihi ön seçer.
        $tour = Tour::query()->active()->where('slug', (string) $request->query('tur'))->first();
        $departure = $tour?->upcomingDepartures()->whereKey((int) $request->query('sefer'))->first();

        return view('reservation.create', [
            'selectedTour' => $tour,
            'selectedDeparture' => $departure,
            'metaTitle' => 'Rezervasyon | Tur Kaydı ve Bilgi Talebi',
            'metaDescription' => 'Katılmak istediğiniz turu ve kişi sayısını bildirin; aynı gün içinde sizi arayıp yerinizi ayıralım.',
            'canonical' => route('reservation.create'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Rezervasyon', 'url' => route('reservation.create')],
            ])],
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['kvkk', 'website', Recaptcha::FIELD]);

        // Yalnız sefer seçildiyse tur ondan türetilir.
        if (blank($data['tour_id'] ?? null) && filled($data['tour_departure_id'] ?? null)) {
            $data['tour_id'] = TourDeparture::query()->whereKey($data['tour_departure_id'])->value('tour_id');
        }

        $reservation = ReservationRequest::query()->create($data + [
            'kvkk_accepted' => true,
            'status' => ReservationRequest::STATUS_NEW,
            'source' => in_array($request->input('source'), ['contact', 'tour'], true) ? $request->input('source') : 'form',
            'page_url' => $this->safePageUrl($request->input('page_url')) ?? $this->safePageUrl($request->headers->get('referer')),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $this->notify($reservation);

        return redirect()->route('reservation.thanks')->with('reservation_sent', true);
    }

    public function thanks(): View
    {
        return view('reservation.thanks', [
            'metaTitle' => 'Talebiniz Alındı',
            'metaDescription' => 'Rezervasyon talebiniz bize ulaştı. En kısa sürede sizinle iletişime geçeceğiz.',
            'canonical' => route('reservation.thanks'),
            'robots' => 'noindex, follow',
        ]);
    }

    /**
     * page_url yönetim panelinde tıklanabilir bağlantı olarak gösterilir; bu yüzden yalnızca
     * bu siteye ait http(s) adresleri kabul edilir (javascript: vb. şemalar reddedilir).
     */
    protected function safePageUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 255) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        return ($parts['host'] ?? null) === request()->getHost() ? $url : null;
    }

    protected function notify(ReservationRequest $reservation): void
    {
        $email = setting('notification_email');

        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->send(new ReservationRequestReceived($reservation));
        } catch (\Throwable $e) {
            Log::warning('Rezervasyon talebi e-postası gönderilemedi: '.$e->getMessage(), ['reservation_id' => $reservation->getKey()]);
        }
    }
}
