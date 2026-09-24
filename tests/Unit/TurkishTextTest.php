<?php

namespace Tests\Unit;

use App\Support\TurkishText;
use PHPUnit\Framework\TestCase;

class TurkishTextTest extends TestCase
{
    public function test_turkish_capital_i_folds(): void
    {
        $this->assertSame('işletme', TurkishText::lower('İşletme'));
        $this->assertStringNotContainsString("\u{0307}", TurkishText::lower('İİİ'));
        $this->assertSame('ışık', TurkishText::lower('IŞIK'));
        $this->assertSame('', TurkishText::lower(null));
    }

    public function test_upper_keeps_turkish_letters(): void
    {
        // mb_strtoupper('Geziyor') "GEZIYOR" verir; marka "GEZİYOR" yazılmalı.
        $this->assertSame('GEZİYOR', TurkishText::upper('Geziyor'));
        $this->assertSame('RTEÜ', TurkishText::upper('rteü'));
        $this->assertSame('IŞIK ŞÖMİNE ÇAĞ', TurkishText::upper('ışık şömine çağ'));
        $this->assertSame('', TurkishText::upper(''));
    }

    public function test_slug_is_ascii(): void
    {
        $this->assertSame('corlu-gardirop-montaji', TurkishText::slug('Çorlu Gardırop Montajı'));
        $this->assertSame('isletme-icin-pos', TurkishText::slug('İşletme İçin POS'));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', TurkishText::slug('Ağaç Şömine Ürünü ÖĞÜN'));
        $this->assertSame('telefon-fotografi', TurkishText::slug('Telefon Fotoğrafı'));
    }
}
