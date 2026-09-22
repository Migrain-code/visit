<?php

namespace App\Services\Seo;

use App\Support\AutomationLog;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Throwable;

/**
 * Sayfayı uygulama içinden render eder ve <main> bloğunu döndürür.
 *
 * Neden gerekli: H1/H2 gibi yapısal öğeleri Blade şablonu üretir, veritabanındaki
 * içerik alanı değil. Yalnız veritabanı alanına bakan bir skorlayıcı "H2 yok" der
 * ve yöneticinin düzeltemeyeceği bir uyarı üretir (spec §7.4'ün tersi: uyarı
 * eyleme dönüşebilir olmalı).
 *
 * Oturum sürücüsü çağrı boyunca 'array'e alınır; aksi hâlde her skorlama turu
 * veritabanına 40 oturum satırı yazar (spec §7.13).
 */
class PageRenderer
{
    private bool $prepared = false;

    public function prepare(): void
    {
        if ($this->prepared) {
            return;
        }

        config(['session.driver' => 'array']);
        $this->prepared = true;
    }

    /** @return string|null <main> içeriği; sayfa 200 dönmezse null */
    public function mainHtml(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $this->prepare();

        $originalRequest = app()->bound('request') ? app('request') : null;

        try {
            $kernel = app(Kernel::class);
            $request = Request::create($url, 'GET', server: ['HTTP_USER_AGENT' => 'VisitSeoScorer/1.0']);

            $response = $kernel->handle($request);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getContent();

            if (preg_match('#<main\b[^>]*>(.*?)</main>#is', $html, $m)) {
                return $m[1];
            }

            return $html;
        } catch (Throwable $e) {
            AutomationLog::error('seo.render', $e->getMessage(), ['url' => $url]);

            return null;
        } finally {
            if ($originalRequest) {
                app()->instance('request', $originalRequest);
            }
        }
    }
}
