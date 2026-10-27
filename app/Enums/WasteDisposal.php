<?php

namespace App\Enums;

enum WasteDisposal: string
{
    case Kept = 'kept';
    case Sold = 'sold';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Kept => __('Kept in store'),
            self::Sold => __('Sold'),
            self::Discarded => __('Discarded'),
        };
    }

    /**
     * Badge colour used when this value is shown in a table.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Kept => 'mute',
            self::Sold => 'good',
            self::Discarded => 'warn',
        };
    }
}
