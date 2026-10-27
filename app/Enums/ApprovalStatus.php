<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Approved => __('Approved'),
            self::Rejected => __('Sent back'),
        };
    }

    /**
     * Badge colour used when this value is shown in a table.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Approved => 'good',
            self::Rejected => 'bad',
        };
    }
}
