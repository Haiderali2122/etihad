<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Finalized => __('Finalized'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warn',
            self::Finalized => 'good',
        };
    }
}
