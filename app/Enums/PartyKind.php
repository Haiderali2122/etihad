<?php

namespace App\Enums;

enum PartyKind: string
{
    case PaperParty = 'paper';
    case Supplier = 'supplier';
    case Mechanic = 'mechanic';
    case Press = 'press';
    case Buyer = 'buyer';
    case PlateProcessor = 'plates';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PaperParty => __('Paper party / mill'),
            self::Supplier => __('Material supplier'),
            self::Mechanic => __('Mechanic / parts vendor'),
            self::Press => __('Outside press'),
            self::Buyer => __('Buyer'),
            self::PlateProcessor => __('Plate processor'),
            self::Other => __('Other'),
        };
    }
}
