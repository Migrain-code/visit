<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/turlar', [TourController::class, 'index'])->name('tours.index');
Route::get('/turlar/{category:slug}', [TourController::class, 'category'])
    ->where('category', '[a-z0-9\-]+')
    ->name('tours.category');
Route::get('/tur-takvimi', [TourController::class, 'calendar'])->name('tours.calendar');
Route::get('/bolgeler', [RegionController::class, 'index'])->name('regions.index');
Route::get('/galeri', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/hakkimizda', [PageController::class, 'about'])->name('about');
Route::get('/sss', [PageController::class, 'faq'])->name('faq');
Route::get('/iletisim', [ContactController::class, 'index'])->name('contact');

Route::get('/rezervasyon', [ReservationController::class, 'create'])->name('reservation.create');
Route::post('/rezervasyon', [ReservationController::class, 'store'])->middleware('throttle:reservation')->name('reservation.store');
Route::get('/rezervasyon/tesekkurler', [ReservationController::class, 'thanks'])->name('reservation.thanks');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// robots.txt AI ajanlarını AÇIKÇA karşılar ve llms.txt adresini duyurur (spec §3.9).
Route::get('/robots.txt', function () {
    $lines = ['User-agent: *', 'Allow: /'];

    foreach (['/admin', '/admin-files/', '/rezervasyon/tesekkurler'] as $disallow) {
        $lines[] = 'Disallow: '.$disallow;
    }

    $lines[] = '';

    foreach (array_keys((array) config('seo.ai_bots', [])) as $bot) {
        $lines[] = 'User-agent: '.$bot;
        $lines[] = 'Allow: /';
        $lines[] = '';
    }

    $lines[] = '# Yapay zeka ajanları için yapılandırılmış özet:';
    $lines[] = '# '.url('/llms.txt');
    $lines[] = '';
    $lines[] = 'Sitemap: '.route('sitemap');
    $lines[] = '';

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// Yolcu listesi (manifesto): yalnız panele giriş yapmış ve seferi görme yetkisi olan kullanıcıya.
// Arama motorlarına kapalıdır (robots.txt) ve yanıt önbelleğe alınmaz.
Route::get('/admin-files/sefer/{departure}/yolcu-listesi', ManifestController::class)
    ->whereNumber('departure')
    ->middleware(['auth', 'cache.headers:no_store,private'])
    ->name('admin.manifest');

// Blog rotaları catch-all'dan ÖNCE gelmeli: /{slug} deseni "/blog" adresini de yakalar.
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/kategori/{category:slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.show');

// Kök dizin slug'ları: /tur-slug, /il-slug, /sayfa-slug ve /il-slug/ilce-slug
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('slug.show');

Route::get('/{province:slug}/{district}', [RegionController::class, 'district'])
    ->where(['province' => '[a-z0-9\-]+', 'district' => '[a-z0-9\-]+'])
    ->name('regions.district');
