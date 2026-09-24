<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\ManifestController;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Site: tek sayfa + iletişim + iş başvurusu
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/iletisim', [ContactController::class, 'index'])->name('contact');
Route::post('/iletisim', [ContactController::class, 'store'])->middleware('throttle:reservation')->name('contact.store');
Route::get('/iletisim/tesekkurler', [ContactController::class, 'thanks'])->name('contact.thanks');

Route::get('/is-basvurusu', [JobApplicationController::class, 'create'])->name('jobs.create');
Route::post('/is-basvurusu', [JobApplicationController::class, 'store'])->middleware('throttle:reservation')->name('jobs.store');
Route::get('/is-basvurusu/tesekkurler', [JobApplicationController::class, 'thanks'])->name('jobs.thanks');

// Eski adresler: rezervasyon formu artık iletişim sayfasında.
Route::redirect('/rezervasyon', '/iletisim', 301);

Route::get('/sitemap.xml', function () {
    $urls = [route('home'), route('contact'), route('jobs.create')];

    return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

Route::get('/robots.txt', function () {
    $lines = ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /admin-files/', '', 'Sitemap: '.route('sitemap'), ''];

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

/*
|--------------------------------------------------------------------------
| Panel yardımcıları (giriş gerekir)
|--------------------------------------------------------------------------
*/

// Yolcu listesi (manifesto): yalnız panele giriş yapmış ve turu görme yetkisi olan kullanıcıya.
// Arama motorlarına kapalıdır (robots.txt) ve yanıt önbelleğe alınmaz.
Route::get('/admin-files/tur/{departure}/yolcu-listesi', ManifestController::class)
    ->whereNumber('departure')
    ->middleware(['auth', 'cache.headers:no_store,private'])
    ->name('admin.manifest');

// İş başvurusu özgeçmişi: özel diskte durur, yalnız yetkili personel indirir.
Route::get('/admin-files/is-basvurusu/{application}/cv', function (JobApplication $application) {
    abort_unless((bool) auth()->user()?->is_active, 403);
    abort_unless(auth()->user()->can('view', $application), 403);
    abort_unless($application->cv_path && Storage::disk('local')->exists($application->cv_path), 404);

    return Storage::disk('local')->download($application->cv_path, 'cv-'.\Illuminate\Support\Str::slug($application->name).'.'.pathinfo($application->cv_path, PATHINFO_EXTENSION));
})->whereNumber('application')->middleware(['auth', 'cache.headers:no_store,private'])->name('admin.job-application.cv');

// PWA manifesti: panel telefonda "ana ekrana ekle" ile uygulama gibi açılır.
Route::get('/admin/manifest.webmanifest', function () {
    return response()->json([
        'name' => site_name().' Yönetim',
        'short_name' => \Illuminate\Support\Str::limit(site_name(), 12, ''),
        'start_url' => '/admin',
        'scope' => '/admin',
        'display' => 'standalone',
        'background_color' => '#0d2544',
        'theme_color' => '#0d2544',
        'lang' => 'tr',
        'icons' => [
            ['src' => asset('images/brand/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => asset('images/brand/icon-512.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('admin.pwa-manifest');
