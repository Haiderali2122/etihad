<?php

namespace App\Enums;

enum BuyerEntryKind: string
{
    case Advance = 'advance';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Advance => __('Advance received'),
            self::Adjustment => __('Adjusted against supply'),
        };
    }
}
