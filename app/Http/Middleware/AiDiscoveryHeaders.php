<?php

namespace App\Http\Middleware;

use App\Models\AiCrawlerVisit;
use App\Support\AutomationLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * AI ajanı keşif katmanı (spec §3.9).
 *
 * 1. Tanınan AI botlarının ziyaretini kaydeder.
 * 2. RFC 8288 Link başlıklarıyla llms.txt ve sitemap.xml adreslerini duyurur —
 *    robots.txt okumayan ajanlar siteyi böyle bulur.
 * 3. Content-Signal başlığıyla kullanım tercihini bildirir.
 *
 * GET'in yanında HEAD de kapsanır; yalnız GET'e bakan bir middleware `curl -I`
 * çağrılarında sessiz kalır ve "500 veriyor" sanılır (spec §7.11).
 */
class AiDiscoveryHeaders
{
    /** Güvenli, gövdesiz de olabilen metotlar. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $response;
        }

        // Panel ve dosya rotalarına başlık basmaya gerek yok.
        if ($request->is('admin', 'admin/*', 'admin-files/*', 'livewire/*', 'build/*', 'storage/*')) {
            return $response;
        }

        $response->headers->set(
            'Link',
            '<'.url('/llms.txt').'>; rel="llms-txt"; type="text/plain", '.
            '<'.url('/sitemap.xml').'>; rel="sitemap"; type="application/xml"',
            false,
        );

        // search=yes: arama sonuçlarında görünebilir. ai-input=yes: cevap üretirken kaynak alınabilir.
        // ai-train=no: model eğitimi için kullanılamaz.
        $response->headers->set('Content-Signal', 'search=yes, ai-input=yes, ai-train=no');

        $this->recordVisit($request, $response);

        return $response;
    }

    private function recordVisit(Request $request, Response $response): void
    {
        try {
            $bot = AiCrawlerVisit::detect($request->userAgent());

            if ($bot) {
                AiCrawlerVisit::record($bot, $request->path(), $response->getStatusCode());
            }
        } catch (Throwable $e) {
            // Kayıt ASLA isteği bozmaz.
            AutomationLog::error('crawler.record', $e->getMessage(), ['path' => $request->path()]);
        }
    }
}
