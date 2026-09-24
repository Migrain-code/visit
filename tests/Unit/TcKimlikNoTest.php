<?php

namespace Tests\Unit;

use App\Rules\TcKimlikNo;
use PHPUnit\Framework\TestCase;

class TcKimlikNoTest extends TestCase
{
    public function test_only_eleven_digits_are_required(): void
    {
        // Kontrol basamağı denetlenmez: 11 hanelik her numara geçer.
        $this->assertTrue(TcKimlikNo::isValid('12345678901'));
        $this->assertTrue(TcKimlikNo::isValid('10000000146'));
        $this->assertTrue(TcKimlikNo::isValid('123 456 789 01'), 'ayraçlar yok sayılır');

        $this->assertFalse(TcKimlikNo::isValid('1234567890'), '10 hane');
        $this->assertFalse(TcKimlikNo::isValid('123456789012'), '12 hane');
        $this->assertFalse(TcKimlikNo::isValid('abcdefghijk'));
        $this->assertFalse(TcKimlikNo::isValid(''));
    }
}
