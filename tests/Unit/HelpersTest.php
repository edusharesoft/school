<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_money_uses_south_asian_grouping(): void
    {
        app()->setLocale('en');

        $this->assertSame('৳ 12,34,567.50', money_bdt(1234567.5));
        $this->assertSame('999.00', money_bdt(999, false));
        $this->assertSame('-1,00,000.00', money_bdt(-100000, false));
    }

    public function test_bangla_digits(): void
    {
        $this->assertSame('২০২৬', bn_digits('2026', true));

        app()->setLocale('bn');
        $this->assertSame('৳ ১,০০০.০০', money_bdt(1000));
    }
}
