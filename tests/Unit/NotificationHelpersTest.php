<?php

namespace Tests\Unit;

use App\Services\Notifications\PhoneNumber;
use App\Services\Notifications\SmsCounter;
use PHPUnit\Framework\TestCase;

class NotificationHelpersTest extends TestCase
{
    public function test_bangladeshi_numbers_are_normalised(): void
    {
        $this->assertSame('8801712345678', PhoneNumber::normalize('01712345678'));
        $this->assertSame('8801712345678', PhoneNumber::normalize('+880 1712-345678'));
        $this->assertSame('8801712345678', PhoneNumber::normalize('8801712345678'));
        $this->assertSame('8801912345678', PhoneNumber::normalize('1912345678'));
        $this->assertNull(PhoneNumber::normalize('01212345678'));   // 012 is not a mobile prefix
        $this->assertNull(PhoneNumber::normalize('0171234567'));    // too short
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_sms_parts_follow_gsm_and_unicode_limits(): void
    {
        $this->assertSame(0, SmsCounter::segments(''));
        $this->assertSame(1, SmsCounter::segments(str_repeat('a', 160)));
        $this->assertSame(2, SmsCounter::segments(str_repeat('a', 161)));
        $this->assertSame(2, SmsCounter::segments(str_repeat('a', 306)));
        $this->assertSame(3, SmsCounter::segments(str_repeat('a', 307)));
        $this->assertSame(2, SmsCounter::segments(str_repeat('{', 81)));  // escaped characters count twice
        $this->assertFalse(SmsCounter::isUnicode('Fee Tk 1,500.00 received.'));
        $this->assertTrue(SmsCounter::isUnicode('ফি জমা হয়েছে'));
        $this->assertSame(1, SmsCounter::segments(str_repeat('ক', 70)));
        $this->assertSame(2, SmsCounter::segments(str_repeat('ক', 71)));
        $this->assertSame(2, SmsCounter::segments(str_repeat('ক', 134)));
        $this->assertSame(3, SmsCounter::segments(str_repeat('ক', 135)));
    }
}
