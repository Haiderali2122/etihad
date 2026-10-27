<?php

namespace App\Enums;

enum WasteStage: string
{
    case Printing = 'printing';
    case Folding = 'folding';
    case Binding = 'binding';
    case Cutting = 'cutting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Printing => __('Printing'),
            self::Folding => __('Folding'),
            self::Binding => __('Binding'),
            self::Cutting => __('Cutting / trimming'),
            self::Other => __('Other'),
        };
    }
}
