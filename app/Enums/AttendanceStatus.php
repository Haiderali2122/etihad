<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Leave = 'leave';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Absent => __('Absent'),
            self::Leave => __('Paid leave'),
            self::Off => __('Off / holiday'),
        };
    }

    /**
     * One-letter mark for the monthly grid.
     */
    public function short(): string
    {
        return match ($this) {
            self::Present => __('P'),
            self::Absent => __('A'),
            self::Leave => __('L'),
            self::Off => __('O'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present => 'good',
            self::Absent => 'bad',
            self::Leave => 'info',
            self::Off => 'mute',
        };
    }
}
