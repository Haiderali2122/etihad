<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One punch (face, palm, finger or card) as the machine sent it: the person's machine user ID and the time.
 */
#[Fillable(['attendance_device_id', 'pin', 'punched_at', 'state', 'verify'])]
class DevicePunch extends Model
{
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AttendanceDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
