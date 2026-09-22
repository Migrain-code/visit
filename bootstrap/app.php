<?php

use App\Http\Middleware\AiDiscoveryHeaders;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Support\AutomationLog;
use App\Support\ProbePaths;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Yönetici paneline ait korumalı dosya rotaları için giriş sayfasına yönlendir.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        // AI ajan keşif başlıkları + bot ziyaret kaydı (spec §3.9).
        $middleware->web(append: [
            AiDiscoveryHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Yönlendirme YALNIZ 404 anında çalışır — global middleware değil (spec §10.6).
         * Geçerli URL'lere maliyeti sıfırdır.
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
                return null;
            }

            $path = $request->path();

            try {
                if ($redirect = Redirect::lookup($path)) {
                    $redirect->registerHit();

                    $target = $redirect->resolveTarget();

                    return redirect()->to(
                        preg_match('#^https?://#i', $target) ? $target : url($target),
                        $redirect->status_code,
                    );
                }

                if (! ProbePaths::isNoise($path)) {
                    NotFoundLog::record($path, $request->headers->get('referer'), $request->userAgent());
                }
            } catch (\Throwable $ex) {
                // Gözlem katmanı isteği ASLA bozmaz (spec §3.8, §10.3).
                AutomationLog::error('notfound.hook', $ex->getMessage(), ['path' => $path]);
            }

            return null; // Normal 404 sayfasına düş.
        });
    })
    ->create();
