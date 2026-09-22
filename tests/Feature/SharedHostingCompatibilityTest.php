<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Paylaşımlı hosting uyumluluğu.
 *
 * Hostingler güvenlik için bazı PHP fonksiyonlarını kapatır (disable_functions).
 * PHP 8'de kapalı bir fonksiyonu çağırmak "Call to undefined function" hatası
 * fırlatır ve başındaki "@" bunu BASTIRMAZ; sayfa çöker. Yerelde her şey açık
 * olduğu için bu hata ancak canlıda görülür — escapeshellarg() bir kez panel
 * sayfasını böyle çökertti. Bu test kodu tarayarak aynı hatanın geri gelmesini önler.
 */
class SharedHostingCompatibilityTest extends TestCase
{
    /** Uygulama kodunda HİÇ kullanılmamalı: canlıda sık kapatılır ve kaçınılabilir. */
    private const BANNED = [
        'escapeshellarg', 'escapeshellcmd', 'exec', 'shell_exec', 'system',
        'passthru', 'popen', 'highlight_file', 'show_source',
    ];

    /** Kullanılabilir ama aynı dosyada function_exists() ile korunmalı. */
    private const GUARDED = [
        'set_time_limit', 'ignore_user_abort', 'proc_open', 'symlink',
        'exif_read_data', 'putenv', 'php_uname', 'disk_free_space',
    ];

    /** @return array<string, string> dosya yolu => içerik */
    private function sources(): array
    {
        $out = [];

        foreach ((new Finder)->files()->in([app_path(), base_path('routes'), resource_path('views')])->name(['*.php']) as $file) {
            $code = $file->getContents();

            // Blade şablonları önce PHP'ye derlenir; {{ }} içindeki çağrılar da yakalansın.
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $code = app('blade.compiler')->compileString($code);
            }

            $out[$file->getRelativePathname()] = $code;
        }

        return $out;
    }

    /**
     * PHP ayrıştırıcısıyla GERÇEK bir fonksiyon çağrısı arar.
     *
     * Yorumlar, metinler, "->exec(" gibi metot çağrıları ve "function exec(" gibi
     * tanımlar sayılmaz; metin araması bunları yanlışlıkla yakalıyordu.
     */
    private function calls(string $code, string $function): bool
    {
        $tokens = token_get_all($code);
        $count = count($tokens);
        $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            if (strtolower(ltrim($token[1], '\\')) !== $function) {
                continue;
            }

            $next = $i + 1;
            while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], $skip, true)) {
                $next++;
            }

            if (($tokens[$next] ?? null) !== '(') {
                continue;
            }

            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && in_array($tokens[$prev][0], $skip, true)) {
                $prev--;
            }

            $before = $tokens[$prev] ?? null;

            if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function test_commonly_disabled_functions_are_not_called(): void
    {
        foreach ($this->sources() as $path => $code) {
            foreach (self::BANNED as $function) {
                $this->assertFalse(
                    $this->calls($code, $function),
                    "{$path} içinde {$function}() çağrılıyor. Paylaşımlı hostingde kapalıysa sayfa çöker."
                );
            }
        }
    }

    public function test_optional_functions_are_guarded(): void
    {
        foreach ($this->sources() as $path => $code) {
            foreach (self::GUARDED as $function) {
                if (! $this->calls($code, $function)) {
                    continue;
                }

                $this->assertStringContainsString(
                    "function_exists('{$function}')",
                    $code,
                    "{$path} içinde {$function}() function_exists() ile korunmadan çağrılıyor."
                );
            }
        }
    }
}
