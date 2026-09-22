<?php

namespace App\Services\Ai;

use App\Support\TurkishText;
use Illuminate\Support\Str;

/**
 * AI çıktısını temizler — MODELE GÜVENİLMEZ (spec §10.1).
 *
 * Prompt'a kural yazmak yetmez; sınırlar kod tarafında zorlanır:
 * slug ASCII, meta_title ≤ 60, meta_description ≤ 155, gövdeden <a> sökülür.
 */
class ContentSanitizer
{
    public function title(?string $value, int $max = 120): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');

        return Str::limit($value, $max, '');
    }

    public function slug(?string $value, ?string $fallbackFrom = null): string
    {
        $slug = TurkishText::slug($value);

        if ($slug === '') {
            $slug = TurkishText::slug($fallbackFrom);
        }

        return Str::limit($slug, 180, '');
    }

    public function metaTitle(?string $value, ?string $fallback = null): string
    {
        $value = $this->title($value ?: $fallback, 200);

        if (mb_strlen($value) <= 60) {
            return $value;
        }

        // Kelime ortasından kesme: son boşluktan kırp.
        $cut = mb_substr($value, 0, 60);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace && $lastSpace > 30 ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,-–—|');
    }

    public function metaDescription(?string $value, ?string $fallback = null): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($value ?: $fallback))) ?? '');

        if (mb_strlen($value) <= 155) {
            return $value;
        }

        $cut = mb_substr($value, 0, 155);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace && $lastSpace > 80 ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,-–—|').'…';
    }

    /**
     * Gövdeyi güvenli etiketlerle sınırlar ve TÜM bağlantıları söker.
     *
     * Link basmanın tek sahibi iç link motorudur (modül 6). AI'ye bırakılırsa tavan,
     * sahiplik ve kapsam kuralları delinir (spec §3.6).
     */
    public function bodyHtml(?string $html): string
    {
        $html = (string) $html;

        // Markdown kod bloğu sarmalayıcısını at.
        $html = preg_replace('/```(?:html)?\s*(.*?)```/s', '$1', $html) ?? $html;

        // Script/style/iframe tamamen silinir.
        $html = preg_replace('#<(script|style|iframe|object|embed)\b.*?</\1>#is', '', $html) ?? $html;

        // Bağlantılar sökülür, metni korunur.
        $html = preg_replace('#<a\b[^>]*>(.*?)</a>#is', '$1', $html) ?? $html;

        // H1 kullanılmaz: sayfanın tek H1'ini şablon basar.
        $html = preg_replace('#<h1\b[^>]*>(.*?)</h1>#is', '<h2>$1</h2>', $html) ?? $html;

        $allowed = '<h2><h3><h4><p><ul><ol><li><strong><b><em><i><blockquote><br><table><thead><tbody><tr><th><td>';
        $html = strip_tags($html, $allowed);

        // Olay işleyicileri ve style nitelikleri temizlenir.
        $html = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html) ?? $html;

        return trim($html);
    }

    /**
     * @param  mixed  $faqs
     * @return array<int, array{question: string, answer: string}>
     */
    public function faqs($faqs, int $max = 8): array
    {
        $out = [];

        foreach ((array) $faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $question = $this->title($faq['question'] ?? $faq['q'] ?? null, 200);
            $answer = trim(strip_tags((string) ($faq['answer'] ?? $faq['a'] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $out[] = ['question' => $question, 'answer' => Str::limit($answer, 800, '')];

            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    public function wordCount(?string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }
}
