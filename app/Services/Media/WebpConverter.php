<?php

namespace App\Services\Media;

use App\Support\AutomationLog;
use App\Support\TurkishText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Yüklenen görselleri WebP'ye çevirir.
 *
 * NEDEN: WebP aynı görsel kalitede JPEG/PNG'den belirgin küçüktür; sayfa hızı ve
 * Core Web Vitals doğrudan etkilenir.
 *
 * GÖRSEL OLMAYAN DOSYALARA DOKUNMAZ. Google servis hesabı JSON'u gibi dosyalar
 * olduğu gibi kaydedilir — aksi hâlde kimlik dosyası bozulurdu.
 *
 * Çevirme başarısız olursa (bozuk dosya, desteklenmeyen biçim, animasyonlu GIF)
 * dosya ORİJİNAL hâliyle kaydedilir; yükleme asla düşmez.
 */
class WebpConverter
{
    /** GD ile okunabilen biçimler. Animasyonlu GIF ve SVG hariç tutulur. */
    private const CONVERTIBLE = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    /** Uzun kenar bu değeri aşarsa küçültülür. */
    private const MAX_EDGE = 2200;

    private const QUALITY = 85;

    public function supported(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    /**
     * Dosyayı diske yazar; görselse WebP'ye çevirir.
     *
     * @return string diskteki göreli yol
     */
    public function store(UploadedFile $file, string $disk, string $directory, ?string $visibility = null): string
    {
        $directory = trim($directory, '/');
        $name = $this->baseName($file);

        $binary = $this->toWebp($file);

        if ($binary === null) {
            // Çevrilemedi: orijinali koru.
            $path = trim($directory.'/'.$name.'.'.$file->getClientOriginalExtension(), '/');
            Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()), $visibility ? ['visibility' => $visibility] : []);

            return $path;
        }

        $path = trim($directory.'/'.$name.'.webp', '/');
        Storage::disk($disk)->put($path, $binary, $visibility ? ['visibility' => $visibility] : []);

        return $path;
    }

    /** @return string|null WebP ikili verisi; çevrilemezse null */
    public function toWebp(UploadedFile $file): ?string
    {
        if (! $this->supported()) {
            return null;
        }

        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        if (! in_array($extension, self::CONVERTIBLE, true)) {
            return null;
        }

        try {
            $path = $file->getRealPath();

            if (! $path || ! is_readable($path)) {
                return null;
            }

            // Animasyonlu GIF: GD yalnız ilk kareyi alır, animasyon kaybolur — dokunma.
            if ($extension === 'gif' && $this->isAnimatedGif($path)) {
                return null;
            }

            $image = @imagecreatefromstring(file_get_contents($path));

            if ($image === false) {
                return null;
            }

            $image = $this->fixOrientation($image, $path, $extension);
            $image = $this->downscale($image);

            // Saydamlığı koru (PNG/WebP).
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $ok = imagewebp($image, null, self::QUALITY);
            $binary = ob_get_clean();
            imagedestroy($image);

            return ($ok && $binary !== false && $binary !== '') ? $binary : null;
        } catch (Throwable $e) {
            AutomationLog::error('media.webp', $e->getMessage(), ['file' => $file->getClientOriginalName()]);

            return null;
        }
    }

    private function baseName(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = TurkishText::slug($original);

        // Dosya adı SEO'ya girer (görsel araması); anlamlı ad + benzersizlik eki.
        return Str::limit($slug ?: 'gorsel', 60, '').'-'.Str::lower(Str::random(8));
    }

    /** Telefon fotoğraflarında EXIF dönüklüğünü düzeltir. */
    private function fixOrientation(\GdImage $image, string $path, string $extension): \GdImage
    {
        if (! in_array($extension, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);
            $orientation = $exif['Orientation'] ?? null;

            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };

            if ($rotated) {
                imagedestroy($image);

                return $rotated;
            }
        } catch (Throwable) {
            // EXIF okunamadı: görseli olduğu gibi bırak.
        }

        return $image;
    }

    private function downscale(\GdImage $image): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $long = max($w, $h);

        if ($long <= self::MAX_EDGE) {
            return $image;
        }

        $ratio = self::MAX_EDGE / $long;
        $newW = max(1, (int) round($w * $ratio));
        $newH = max(1, (int) round($h * $ratio));

        /*
         * imagecopyresampled kullanılır, imagescale DEĞİL: imagescale bazı GD
         * yapılarında (bu makinedeki dahil) sessizce false döner ve görsel
         * küçültülmeden geçer.
         */
        $resized = imagecreatetruecolor($newW, $newH);

        if ($resized === false) {
            return $image;
        }

        // Saydamlığı küçültme sırasında koru.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));

        if (! imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h)) {
            imagedestroy($resized);

            return $image;
        }

        imagedestroy($image);

        return $resized;
    }

    private function isAnimatedGif(string $path): bool
    {
        $contents = file_get_contents($path);

        return $contents !== false && substr_count($contents, "\x00\x21\xF9\x04") > 1;
    }
}
