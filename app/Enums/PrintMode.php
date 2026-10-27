<?php

namespace App\Enums;

enum PrintMode: string
{
    case InHouse = 'in_house';
    case Outsource = 'outsource';

    public function label(): string
    {
        return match ($this) {
            self::InHouse => __('In-house'),
            self::Outsource => __('Outsource'),
        };
    }

    /**
     * Badge colour used when this value is shown in a table.
     */
    public function tone(): string
    {
        return match ($this) {
            self::InHouse => 'info',
            self::Outsource => 'warn',
        };
    }
}
