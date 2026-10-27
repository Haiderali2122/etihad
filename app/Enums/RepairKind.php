<?php

namespace App\Enums;

enum RepairKind: string
{
    case Repair = 'repair';
    case NewPart = 'new_part';

    public function label(): string
    {
        return match ($this) {
            self::Repair => __('Repair'),
            self::NewPart => __('New part'),
        };
    }
}
