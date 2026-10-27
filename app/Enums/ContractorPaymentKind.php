<?php

namespace App\Enums;

enum ContractorPaymentKind: string
{
    case Advance = 'advance';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Advance => __('Advance'),
            self::Payment => __('Payment'),
        };
    }
}
