<?php

namespace App\Enums;

/**
 * What a line in Younus ka khata is for. Every line is rims × a rate per rim.
 */
enum RimMove: string
{
    case ToPress = 'to_press';
    case ToFolding = 'to_folding';
    case ToOutside = 'to_outside';
    case Printing = 'printing';
    case FromOutside = 'from_outside';

    public function label(): string
    {
        return match ($this) {
            self::ToPress => __('Godown → our press'),
            self::ToFolding => __('Our press → folding'),
            self::ToOutside => __('Godown → outside press'),
            self::Printing => __('Outside printing charge'),
            self::FromOutside => __('Outside press → back for folding'),
        };
    }

    /**
     * The Cash-out head the payment is filed under.
     */
    public function head(): string
    {
        return $this === self::Printing ? 'Outside printing' : 'Loading labour';
    }

    public function tone(): string
    {
        return match ($this) {
            self::Printing => 'warn',
            self::ToOutside, self::FromOutside => 'info',
            default => 'mute',
        };
    }
}
