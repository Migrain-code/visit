<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sunucudaki kodun hangi commit'te olduğunu ve derlenmiş dosyaların ne zaman
 * yüklendiğini söyler.
 *
 * Terminal olmayan bir hostingde kodun güncellenip güncellenmediğini görmenin başka
 * yolu yok. Bir kez yalnız public/build yüklenip sayfa şablonları eski kaldı; site
 * ikonsuz ve form çalışmaz hâle geldi, dışarıdan fark edilmedi.
 *
 * git komutu ÇALIŞTIRILMAZ (exec kapalı olabilir): .git klasöründeki dosyalar okunur.
 */
class DeploymentInfo
{
    private string $gitDir;

    private string $manifest;

    public function __construct(?string $gitDir = null, ?string $manifest = null)
    {
        $this->gitDir = rtrim($gitDir ?? base_path('.git'), '/');
        $this->manifest = $manifest ?? public_path('build/manifest.json');
    }

    public function commit(): ?string
    {
        $ref = $this->headRef();

        if ($ref === null) {
            return null;
        }

        // HEAD doğrudan bir commit'i gösteriyor olabilir (detached).
        if (preg_match('/^[0-9a-f]{40}$/', $ref)) {
            return $ref;
        }

        $loose = $this->gitDir.'/'.$ref;

        if (is_file($loose)) {
            $hash = trim((string) @file_get_contents($loose));

            return preg_match('/^[0-9a-f]{40}$/', $hash) ? $hash : null;
        }

        return $this->fromPackedRefs($ref);
    }

    public function shortCommit(): ?string
    {
        $commit = $this->commit();

        return $commit ? substr($commit, 0, 7) : null;
    }

    /** Kodun son güncellendiği an: dal işaretçisinin değiştiği zaman. */
    public function codeUpdatedAt(): ?Carbon
    {
        $ref = $this->headRef();

        foreach (array_filter([
            $ref ? $this->gitDir.'/'.$ref : null,
            $this->gitDir.'/ORIG_HEAD',
            $this->gitDir.'/packed-refs',
        ]) as $file) {
            if (is_file($file)) {
                return Carbon::createFromTimestamp((int) filemtime($file));
            }
        }

        return null;
    }

    /** Derlenmiş dosyaların (public/build) yüklendiği an. */
    public function buildUploadedAt(): ?Carbon
    {
        return is_file($this->manifest) ? Carbon::createFromTimestamp((int) filemtime($this->manifest)) : null;
    }

    private function headRef(): ?string
    {
        try {
            $head = trim((string) @file_get_contents($this->gitDir.'/HEAD'));
        } catch (Throwable) {
            return null;
        }

        if ($head === '') {
            return null;
        }

        return str_starts_with($head, 'ref: ') ? trim(substr($head, 5)) : $head;
    }

    private function fromPackedRefs(string $ref): ?string
    {
        $packed = $this->gitDir.'/packed-refs';

        if (! is_file($packed)) {
            return null;
        }

        foreach (file($packed, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/', $line, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
