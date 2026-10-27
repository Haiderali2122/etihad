<?php

namespace App\Enums;

enum PaperIssueKind: string
{
    case InHouse = 'in_house';
    case Outsource = 'outsource';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::InHouse => __('In-house'),
            self::Outsource => __('Outsource'),
            self::Return => __('Return to supplier'),
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
            self::Return => 'mute',
        };
    }
}
