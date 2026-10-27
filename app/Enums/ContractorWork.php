<?php

namespace App\Enums;

enum ContractorWork: string
{
    case Folding = 'folding';
    case Binding = 'binding';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Folding => __('Folding'),
            self::Binding => __('Binding'),
            self::Both => __('Folding and binding'),
        };
    }
}
