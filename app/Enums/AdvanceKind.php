<?php

namespace App\Enums;

enum AdvanceKind: string
{
    case MidMonth = 'mid_month';
    case AdHoc = 'ad_hoc';
    case Foreman = 'foreman';

    public function label(): string
    {
        return match ($this) {
            self::MidMonth => __('Mid-month advance'),
            self::AdHoc => __('Advance'),
            self::Foreman => __('Through foreman'),
        };
    }
}
