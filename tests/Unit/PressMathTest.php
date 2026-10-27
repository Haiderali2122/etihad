<?php

namespace Tests\Unit;

use App\Support\PressMath;
use PHPUnit\Framework\TestCase;

class PressMathTest extends TestCase
{
    public function test_rims_for_a_job_adds_wastage(): void
    {
        // 10 formats × 18,000 copies ÷ 500 = 360 rims, + 3% = 370.8 → 371
        $this->assertSame(371, PressMath::rimsFor(10, 18000, 3));
        $this->assertSame(360, PressMath::rimsFor(10, 18000));
    }

    public function test_bleach_card_uses_packets_of_100(): void
    {
        $this->assertSame(40, PressMath::rimsFor(1, 4000, 0, 100));
    }

    public function test_money_rounds_to_nearest_ten(): void
    {
        $this->assertSame(22910, PressMath::roundToTen(22905));
        $this->assertSame(22900, PressMath::roundToTen(22904));
        $this->assertSame(22900, PressMath::roundToTen(22900));
    }
}
