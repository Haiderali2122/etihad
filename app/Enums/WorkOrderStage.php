<?php

namespace App\Enums;

enum WorkOrderStage: string
{
    case Waiting = 'waiting';
    case Printing = 'printing';
    case Folding = 'folding';
    case Binding = 'binding';
    case Ready = 'ready';
    case Dispatched = 'dispatched';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('Waiting approval'),
            self::Printing => __('Printing'),
            self::Folding => __('Folding'),
            self::Binding => __('Binding'),
            self::Ready => __('Ready to load'),
            self::Dispatched => __('Dispatched'),
        };
    }

    /**
     * Badge colour used when this value is shown in a table.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Waiting => 'warn',
            self::Printing => 'info',
            self::Folding => 'info',
            self::Binding => 'info',
            self::Ready => 'good',
            self::Dispatched => 'mute',
        };
    }

    /**
     * The stage an order moves to next, or null once dispatched.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Waiting => null,
            self::Printing => self::Folding,
            self::Folding => self::Binding,
            self::Binding => self::Ready,
            self::Ready => self::Dispatched,
            self::Dispatched => null,
        };
    }
}
