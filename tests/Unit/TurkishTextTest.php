<?php

namespace Tests\Unit;

use App\Support\PathNormalizer;
use App\Support\TurkishText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurkishTextTest extends TestCase
{
    /**
     * Spec §7.1 — canlıdan alınmış GERÇEK duplicate çiftleri.
     * Tam eşleşmeli filtre bunların dördünü de kaçırmıştı; gövdelemeli
     * Jaccard hepsini 1.0 vermelidir.
     *
     * @return array<string, array{string, string}>
     */
    public static function realDuplicatePairs(): array
    {
        return [
            'soru eki' => ['Dijital Menünün Katkıları Nelerdir?', 'Dijital Menünün Katkıları'],
            'çoğul + edat' => ['Restoran POS Fiyatları 2024', 'Restoranlar İçin POS Fiyatları'],
            'bulunma hâli' => ['Restoranlarda Online Sipariş Yönetimi', 'Restoranlar İçin Online Sipariş Yönetimi'],
            'fiil eki' => ['Ciroyu Artırmanın Yolları', 'Ciroyu Artırma Yolları'],
        ];
    }

    #[DataProvider('realDuplicatePairs')]
    public function test_real_duplicate_pairs_are_caught(string $a, string $b): void
    {
        $score = TurkishText::similarity($a, $b);

        $this->assertSame(1.0, $score, "'{$a}' ↔ '{$b}' tekrar olarak yakalanmalı, skor: {$score}");
    }

    public function test_different_topics_are_not_blocked(): void
    {
        $threshold = 0.55;

        $pairs = [
            ['Gardırop Montajında Dikkat Edilecekler', 'Çorlu\'da Taşınma Sonrası Mobilya Kurulumu'],
            ['IKEA PAX Gardırop Kurulum Rehberi', 'Ofis Taşımada Mobilya Sökme'],
            ['Baza Montajı Ne Kadar Sürer', 'TV Ünitesi Duvara Nasıl Sabitlenir'],
        ];

        foreach ($pairs as [$a, $b]) {
            $score = TurkishText::similarity($a, $b);
            $this->assertLessThan($threshold, $score, "'{$a}' ↔ '{$b}' engellenmemeli, skor: {$score}");
        }
    }

    public function test_turkish_capital_i_folds(): void
    {
        // mb_strtolower('İ') → "i" + U+0307; elle eşleme olmazsa bu test düşer (spec §7.5).
        $this->assertSame(TurkishText::tokens('İşletme'), TurkishText::tokens('işletme'));
        $this->assertSame(TurkishText::tokens('İSTANBUL'), TurkishText::tokens('istanbul'));
        $this->assertSame('işletme', TurkishText::lower('İşletme'));  // lower() Türkçe harfi korur; ASCII'ye indirme slug()'ın işi
        $this->assertStringNotContainsString("\u{0307}", TurkishText::lower('İİİ'));

        // Türkçe I → ı (noktasız)
        $this->assertSame('ışık', TurkishText::lower('IŞIK'));
    }

    public function test_boundary_scores(): void
    {
        $this->assertSame(1.0, TurkishText::similarity('Gardırop Montajı', 'Gardırop Montajı'));
        $this->assertSame(0.0, TurkishText::similarity('Gardırop Montajı', 'Bulut Bilişim Güvenliği'));

        // Boş veya anlamsız başlık ASLA tekrar sayılmaz (spec §9).
        $this->assertSame(0.0, TurkishText::similarity('', 'Gardırop Montajı'));
        $this->assertSame(0.0, TurkishText::similarity('Gardırop Montajı', null));
        $this->assertSame(0.0, TurkishText::similarity('ve ile için', 'Gardırop Montajı'));
    }

    public function test_stop_words_and_short_words_are_dropped(): void
    {
        $this->assertSame(TurkishText::tokens('Gardırop Montajı'), TurkishText::tokens('Gardırop ve Montajı için'));
    }

    public function test_slug_is_ascii(): void
    {
        $this->assertSame('corlu-gardirop-montaji', TurkishText::slug('Çorlu Gardırop Montajı'));
        $this->assertSame('isletme-icin-pos', TurkishText::slug('İşletme İçin POS'));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', TurkishText::slug('Ağaç Şömine Ürünü ÖĞÜN'));
    }

    public function test_path_normalisation_and_hash(): void
    {
        $this->assertSame('/tekirdag/corlu', PathNormalizer::normalize('/tekirdag/corlu/'));
        $this->assertSame('/tekirdag/corlu', PathNormalizer::normalize('https://ornek.com/tekirdag/corlu?utm=1#x'));
        $this->assertSame('/tekirdag/corlu', PathNormalizer::normalize('//tekirdag//corlu'));
        $this->assertSame('/', PathNormalizer::normalize(''));
        $this->assertSame('/', PathNormalizer::normalize('/'));

        $this->assertSame(PathNormalizer::hash('/a/b'), PathNormalizer::hash('/a/b/'));
        $this->assertSame(md5('/a/b'), PathNormalizer::hash('/A/B/'));
    }

    public function test_path_similarity_favours_last_segment(): void
    {
        $this->assertGreaterThan(0.8, PathNormalizer::similarity('/eski-bolge/corlu', '/tekirdag/corlu'));
        $this->assertSame(1.0, PathNormalizer::similarity('/tekirdag/corlu', '/tekirdag/corlu/'));
        $this->assertLessThan(0.5, PathNormalizer::similarity('/tekirdag/corlu', '/hakkimizda'));
    }
}
