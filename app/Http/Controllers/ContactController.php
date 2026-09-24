<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationRequestReceived;
use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use App\Services\Security\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * İletişim sayfası ve formu. Form bir ÖN TALEPTİR: koltuk ayırmaz, panele
 * "İletişim Talepleri" olarak düşer; personel arayıp kaydı açar.
 */
class ContactController extends Controller
{
    public function index(Request $request): View
    {
        // Tur kartındaki "Katıl" bağlantısı turu ön seçer.
        $selected = TourDeparture::query()->bookable()->whereKey((int) $request->query('tur'))->first();

        return view('contact', [
            'selectedTour' => $selected,
            'metaTitle' => 'İletişim',
            'metaDescription' => 'Turlarımız hakkında bilgi almak ve katılmak için bize telefon, WhatsApp, Instagram ya da form üzerinden ulaşın.',
            'canonical' => route('contact'),
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['kvkk', 'website', Recaptcha::FIELD]);

        $reservation = ReservationRequest::query()->create($data + [
            'kvkk_accepted' => true,
            'status' => ReservationRequest::STATUS_NEW,
            'source' => 'contact',
            'page_url' => $this->safePageUrl($request->input('page_url')) ?? $this->safePageUrl($request->headers->get('referer')),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $this->notify($reservation);

        return redirect()->route('contact.thanks')->with('reservation_sent', true);
    }

    public function thanks(): View
    {
        return view('thanks', [
            'heading' => 'Mesajınız bize ulaştı',
            'text' => 'En kısa sürede sizi arayacağız. Acele ediyorsanız WhatsApp\'tan da yazabilirsiniz.',
            'metaTitle' => 'Mesajınız Alındı',
            'metaDescription' => 'Talebiniz bize ulaştı. En kısa sürede sizinle iletişime geçeceğiz.',
            'canonical' => route('contact.thanks'),
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
            Log::warning('İletişim talebi e-postası gönderilemedi: '.$e->getMessage(), ['reservation_id' => $reservation->getKey()]);
        }
    }
}
