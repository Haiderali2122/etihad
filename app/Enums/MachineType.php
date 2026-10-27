<?php

namespace App\Enums;

enum MachineType: string
{
    case Printing = 'printing';
    case Binding = 'binding';

    public function label(): string
    {
        return match ($this) {
            self::Printing => __('Printing'),
            self::Binding => __('Binding'),
        };
    }
}
