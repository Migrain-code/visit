<?php

namespace App\Providers;

use App\Models\AiCrawlerVisit;
use App\Models\AiGeneration;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\DepartureVehicle;
use App\Models\District;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\InternalLinkRule;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Passenger;
use App\Models\Province;
use App\Models\Redirect;
use App\Models\ReservationRequest;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\CatalogPolicy;
use App\Policies\ContentPolicy;
use App\Policies\DepartureVehiclePolicy;
use App\Policies\PassengerPolicy;
use App\Policies\ReservationRequestPolicy;
use App\Policies\SeoPolicy;
use App\Policies\TourDeparturePolicy;
use App\Policies\TourGroupPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use App\Support\Queue\SharedHostingWorker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hosting pcntl fonksiyonlarını kapatıyor; Laravel'in işçisi bunlarla çöküyordu.
        $this->app->extend('queue.worker', fn (Worker $worker) => SharedHostingWorker::from($worker));

        // İstek başına bir kez: ayarlar ve menü verisi. "scoped" kayıtlar her kuyruk
        // işinin başında da sıfırlanır, böylece uzun yaşayan işçi eski veriyle kalmaz.
        $this->app->scoped(Setting::MEMO, fn () => Setting::loadFromStore());

        // Ana sayfa hem kendisi hem içindeki personel kartları için aynı listeyi ister.
        $this->app->scoped('site.staff', fn () => User::query()
            ->public()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->ordered()
            ->get());

        $this->app->scoped('site.nav', fn () => [
            'navCategories' => TourCategory::query()->active()->ordered()->get(['id', 'name', 'slug', 'icon']),
            'navTours' => Tour::query()->active()->where('is_featured', true)->ordered()->limit(8)->get(['id', 'title', 'slug', 'duration_days', 'duration_nights']),
            'navProvinces' => Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name,slug')->get(['id', 'name', 'slug']),
            'footerPages' => Page::query()->active()->where('show_in_footer', true)->ordered()->get(['id', 'title', 'slug']),
        ]);
    }

    /**
     * Yetki ilkeleri.
     *
     * Model başına ayrı dosya yerine ortak ilkeler kullanılır (içerik, katalog, SEO).
     * Onlarca neredeyse aynı dosya bakımı zorlaştırır ve bir tanesini güncellemeyi
     * unutmak sessiz bir yetki açığı yaratır. Yolcu verisine dokunan modellerin
     * (sefer, grup, yolcu, talep) ise kendi ilkesi vardır: kuralları birbirinden farklıdır.
     */
    private function registerPolicies(): void
    {
        $content = [
            Province::class, District::class,
            Page::class, Blog::class, BlogCategory::class,
            GalleryItem::class, GalleryCategory::class,
            Testimonial::class, Faq::class, Feature::class,
        ];

        foreach ($content as $model) {
            Gate::policy($model, ContentPolicy::class);
        }

        foreach ([Tour::class, TourCategory::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }

        $seo = [
            SeoKeyword::class, SeoTarget::class,
            InternalLinkRule::class, AiGeneration::class,
            Redirect::class, NotFoundLog::class,
            AiCrawlerVisit::class,
        ];

        foreach ($seo as $model) {
            Gate::policy($model, SeoPolicy::class);
        }

        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(TourDeparture::class, TourDeparturePolicy::class);
        Gate::policy(DepartureVehicle::class, DepartureVehiclePolicy::class);
        Gate::policy(TourGroup::class, TourGroupPolicy::class);
        Gate::policy(Passenger::class, PassengerPolicy::class);
        Gate::policy(ReservationRequest::class, ReservationRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        $this->registerPolicies();

        RateLimiter::for('reservation', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Header / footer / mobil menü için ortak veriler.
        // Üç görünüm aynı veriyi kullanır; sorgular üç kez değil, istek başına bir kez çalışır.
        View::composer(['partials.header', 'partials.footer', 'partials.mobile-nav'], function ($view) {
            $view->with($this->app->make('site.nav'));
        });

        /*
         * Sitede gösterilecek personel.
         *
         * Müşteri, ana numaraya değil doğrudan ilgili kişiye ulaşabilsin diye
         * telefonu girilmiş ve "web sitesinde göster" işaretli personel yayınlanır.
         * Telefonu olmayan kayıt listelenmez — tıklanacak bir şey olmadan kart göstermek
         * ziyaretçiyi çıkmaza sokar.
         */
        View::composer(['partials.staff-cards', 'contact', 'home'], function ($view) {
            $view->with('staff', $this->app->make('site.staff'));
        });

        View::composer('partials.reservation-form', function ($view) {
            $view->with([
                'formProvinces' => Province::query()->active()->ordered()->with('activeDistricts:id,province_id,name')->get(['id', 'name']),
                'formTours' => Tour::query()->active()->ordered()->get(['id', 'title']),
                'kvkkPage' => Page::query()->active()->where('slug', 'like', '%kvkk%')->first(['id', 'title', 'slug']),
            ]);
        });
    }
}
