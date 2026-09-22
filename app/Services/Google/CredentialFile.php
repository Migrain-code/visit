<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Google servis hesabı JSON dosyasının doğrulanması ve saklanması.
 *
 * GÜVENLİK: dosya "local" diskine yazılır; bu diskin kökü storage/app/private'dır
 * ve web sunucusundan ERİŞİLEMEZ. Dosya özel anahtar içerir; public diske,
 * log'a veya veritabanına ASLA yazılmaz (spec §10.2).
 */
class CredentialFile
{
    public const DIRECTORY = 'google';

    /** Servis hesabı JSON'unda bulunması zorunlu alanlar. */
    private const REQUIRED = ['type', 'project_id', 'private_key', 'client_email'];

    /**
     * Yüklenen dosyanın gerçekten bir servis hesabı anahtarı olduğunu doğrular.
     *
     * @return array{client_email: string, project_id: string}
     *
     * @throws RuntimeException geçersizse
     */
    public function validate(string $relativePath): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($relativePath)) {
            throw new RuntimeException('Yüklenen dosya bulunamadı.');
        }

        try {
            $data = json_decode($disk->get($relativePath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Dosya geçerli bir JSON değil. Google Cloud\'dan indirdiğiniz anahtar dosyasını olduğu gibi yükleyin.');
        }

        if (! is_array($data)) {
            throw new RuntimeException('Dosya geçerli bir JSON nesnesi değil.');
        }

        foreach (self::REQUIRED as $field) {
            if (blank($data[$field] ?? null)) {
                throw new RuntimeException(
                    'Bu bir servis hesabı anahtarı değil: "'.$field.'" alanı eksik. '.
                    'Google Cloud → IAM ve Yönetici → Hizmet Hesapları → Anahtarlar → JSON ile indirilen dosyayı yükleyin.'
                );
            }
        }

        if ($data['type'] !== 'service_account') {
            throw new RuntimeException('Dosyanın türü "service_account" değil (bulunan: "'.$data['type'].'"). OAuth istemci dosyası yüklemiş olabilirsiniz.');
        }

        if (! str_contains((string) $data['private_key'], 'PRIVATE KEY')) {
            throw new RuntimeException('Dosyada geçerli bir özel anahtar yok.');
        }

        return [
            'client_email' => (string) $data['client_email'],
            'project_id' => (string) $data['project_id'],
        ];
    }

    /** Eski kimlik dosyasını siler (yenisi yüklendiğinde çağrılır). */
    public function forget(?string $relativePath): void
    {
        if (blank($relativePath) || $relativePath === null) {
            return;
        }

        $disk = Storage::disk('local');

        // Yalnız bizim yazdığımız klasördeki dosyayı sil; elle konulan yola dokunma.
        if (str_starts_with($relativePath, self::DIRECTORY.'/') && $disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }
    }
}
