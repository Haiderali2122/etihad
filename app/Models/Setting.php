<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-controlled key/value settings, e.g. the manager's daily spending limit.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const ManagerDailyLimit = 'manager_daily_limit';

    public const DefaultWastagePercent = 'default_wastage_percent';

    /** Overtime hourly rate = (salary ÷ days in month) ÷ divisor × multiplier. */
    public const OvertimeDivisor = 'payroll_ot_divisor';

    public const OvertimeMultiplier = 'payroll_ot_multiplier';

    /** Hours in a normal day; time worked beyond this counts as overtime. */
    public const StandardHours = 'payroll_standard_hours';

    /** How days with no attendance entry are treated: "present" or "absent". */
    public const UnmarkedDaysAs = 'payroll_unmarked_as';

    public const MidMonthAdvanceDay = 'payroll_mid_month_day';

    public const MidMonthAdvancePercent = 'payroll_mid_month_percent';

    public const PayDay = 'payroll_pay_day';

    /**
     * Payroll rules with the defaults from Haider's notes; all of them are still to be confirmed with the owner.
     *
     * @return array{ot_divisor: float, ot_multiplier: float, standard_hours: float, unmarked_as: string, mid_month_day: int, mid_month_percent: float, pay_day: int}
     */
    public static function payrollRules(): array
    {
        return [
            'ot_divisor' => (float) static::get(self::OvertimeDivisor, 6),
            'ot_multiplier' => (float) static::get(self::OvertimeMultiplier, 1),
            'standard_hours' => (float) static::get(self::StandardHours, 8),
            'unmarked_as' => (string) static::get(self::UnmarkedDaysAs, 'present'),
            'mid_month_day' => (int) static::get(self::MidMonthAdvanceDay, 22),
            'mid_month_percent' => (float) static::get(self::MidMonthAdvancePercent, 50),
            'pay_day' => (int) static::get(self::PayDay, 10),
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
