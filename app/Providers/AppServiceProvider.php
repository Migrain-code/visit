<?php

namespace App\Providers;

use App\Models\DepartureVehicle;
use App\Models\JobApplication;
use App\Models\Passenger;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\DepartureVehiclePolicy;
use App\Policies\JobApplicationPolicy;
use App\Policies\PassengerPolicy;
use App\Policies\ReservationRequestPolicy;
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

        // İstek başına bir kez: ayarlar. "scoped" kayıtlar her kuyruk işinin başında da
        // sıfırlanır, böylece uzun yaşayan işçi eski veriyle kalmaz.
        $this->app->scoped(Setting::MEMO, fn () => Setting::loadFromStore());

        // Sitedeki formların tur listesi: header, iletişim ve ana sayfa aynı listeyi ister.
        $this->app->scoped('site.tours', fn () => TourDeparture::query()
            ->bookable()
            ->withSeatStats()
            ->ordered()
            ->get());
    }

    /**
     * Yetki ilkeleri. Yolcu verisine dokunan modellerin (tur, grup, yolcu, talep)
     * kendi ilkesi vardır: kuralları birbirinden farklıdır.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(TourDeparture::class, TourDeparturePolicy::class);
        Gate::policy(DepartureVehicle::class, DepartureVehiclePolicy::class);
        Gate::policy(TourGroup::class, TourGroupPolicy::class);
        Gate::policy(Passenger::class, PassengerPolicy::class);
        Gate::policy(ReservationRequest::class, ReservationRequestPolicy::class);
        Gate::policy(JobApplication::class, JobApplicationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        $this->registerPolicies();

        RateLimiter::for('reservation', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        View::composer(['partials.contact-form'], function ($view) {
            $view->with('formTours', $this->app->make('site.tours'));
        });
    }
}
