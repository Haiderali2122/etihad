<?php

namespace App\Enums;

enum ContractorJobKind: string
{
    case Folding = 'folding';
    case Binding = 'binding';

    public function label(): string
    {
        return match ($this) {
            self::Folding => __('Folding'),
            self::Binding => __('Binding'),
        };
    }
}
