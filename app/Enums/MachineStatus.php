<?php

namespace App\Enums;

enum MachineStatus: string
{
    case Running = 'running';
    case UnderRepair = 'under_repair';
    case Idle = 'idle';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::UnderRepair => __('Under repair'),
            self::Idle => __('Idle'),
        };
    }

    /**
     * Badge colour used when this value is shown in a table.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Running => 'good',
            self::UnderRepair => 'bad',
            self::Idle => 'mute',
        };
    }
}
