<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Görselin küçültülmüş kopyasını üretir ve adresini döndürür.
 *
 * Ana sayfa görseli 1920×1440 ve 199 KB'tı; mobilde üstünde %90 koyu bir katman
 * olmasına rağmen tam boyutuyla iniyordu (PageSpeed LCP: 8,5 sn). Kopyalar İLK
 * istekte bir kez üretilir, sonra diskten statik dosya olarak sunulur.
 *
 * Güvenli geri dönüş: kopya üretilemezse (GD yok, dosya yok, yazma izni yok)
 * özgün görselin adresi döner — sayfa asla kırılmaz.
 */
class ImageVariants
{
    public const DIRECTORY = '_variants';

    /**
     * @param  string|null  $path  public diskteki yol ("site/hero.webp") ya da images/... yolu
     * @param  int|null  $height  verilirse görsel bu orana ORTADAN kırpılır (cover)
     */
    public function url(?string $path, int $width, ?int $height = null, int $quality = 72): ?string
    {
        $original = media_url($path);

        if ($original === null || ! $this->supported()) {
            return $original;
        }

        try {
            [$disk, $relative] = $this->locate((string) $path);

            if ($disk === null) {
                return $original;
            }

            $source = $disk->path($relative);
            $variant = $this->variantPath($relative, $source, $width, $height, $quality);
            $public = Storage::disk('public');

            if (! $public->exists($variant) && ! $this->generate($source, $public->path($variant), $width, $height, $quality)) {
                return $original;
            }

            return $public->url($variant);
        } catch (Throwable) {
            return $original;
        }
    }

    public function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /** @return array{0: Filesystem|null, 1: string} */
    private function locate(string $path): array
    {
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return [null, ''];
        }

        if (Str::startsWith($path, ['images/', '/images/'])) {
            $relative = ltrim($path, '/');

            return is_file(public_path($relative))
                ? [Storage::build(['driver' => 'local', 'root' => public_path()]), $relative]
                : [null, ''];
        }

        $public = Storage::disk('public');

        return $public->exists($path) ? [$public, $path] : [null, ''];
    }

    /** Ad, kaynak değişince değişir: yeni görsel yüklenince eski kopya kullanılmaz. */
    private function variantPath(string $relative, string $source, int $width, ?int $height, int $quality): string
    {
        $fingerprint = substr(md5($relative.'|'.@filemtime($source).'|'.@filesize($source).'|'.$quality), 0, 10);
        $name = Str::slug(pathinfo($relative, PATHINFO_FILENAME)) ?: 'gorsel';
        $size = $height ? "{$width}x{$height}" : "{$width}w";

        return self::DIRECTORY."/{$name}-{$fingerprint}-{$size}.webp";
    }

    private function generate(string $source, string $target, int $width, ?int $height, int $quality): bool
    {
        $data = @file_get_contents($source);
        $image = $data !== false ? @imagecreatefromstring($data) : false;

        if (! $image) {
            return false;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        // Kırpma alanı: hedef oran verildiyse ortadan kes, verilmediyse tamamını al.
        [$cropX, $cropY, $cropW, $cropH] = [0, 0, $srcW, $srcH];

        if ($height) {
            $targetRatio = $width / $height;

            if ($srcW / $srcH > $targetRatio) {
                $cropW = (int) round($srcH * $targetRatio);
                $cropX = (int) round(($srcW - $cropW) / 2);
            } else {
                $cropH = (int) round($srcW / $targetRatio);
                $cropY = (int) round(($srcH - $cropH) / 2);
            }
        }

        // Asla büyütme: kaynaktan büyük kopya hem bulanık hem daha ağır olur.
        $outW = min($width, $cropW);
        $outH = $height ? (int) round($outW * $height / $width) : (int) round($cropH * $outW / $cropW);

        $canvas = imagecreatetruecolor($outW, $outH);
        imagecopyresampled($canvas, $image, 0, 0, $cropX, $cropY, $outW, $outH, $cropW, $cropH);
        imagedestroy($image);

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            imagedestroy($canvas);

            return false;
        }

        // Önce geçici dosyaya yaz, sonra taşı: aynı anda gelen iki istek yarım dosya görmesin.
        $temp = $target.'.'.Str::random(6).'.tmp';
        $ok = imagewebp($canvas, $temp, $quality);
        imagedestroy($canvas);

        if (! $ok || ! @rename($temp, $target)) {
            @unlink($temp);

            return false;
        }

        return true;
    }
}
