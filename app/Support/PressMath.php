<?php

namespace App\Support;

/**
 * Calculation rules given by Etihad during scoping (6 October 2026).
 */
class PressMath
{
    /**
     * Rims needed for a job: formats × copies ÷ sheets per rim, plus wastage %.
     */
    public static function rimsFor(float $formats, int $copies, float $wastagePercent = 0, int $sheetsPerUnit = 500): int
    {
        if ($sheetsPerUnit <= 0) {
            return 0;
        }

        return (int) round($formats * $copies / $sheetsPerUnit * (1 + $wastagePercent / 100));
    }

    /**
     * Round money to the nearest 10: Rs 22,905 → 22,910; Rs 22,904 → 22,900.
     */
    public static function roundToTen(float|int $amount): int
    {
        return (int) (round($amount / 10, 0, PHP_ROUND_HALF_UP) * 10);
    }
}
