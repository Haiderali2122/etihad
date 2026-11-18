<?php

namespace App\Models;

use App\Models\Concerns\RecordsDeletion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plates a processor sent back on one date. A plate job's returned total is the sum of its returns.
 */
#[Fillable(['plate_job_id', 'returned_on', 'plates', 'note', 'created_by'])]
class PlateReturn extends Model
{
    use RecordsDeletion;

    protected function casts(): array
    {
        return [
            'returned_on' => 'date',
            'plates' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep the job's returned total (shown on the Plates and Processors tabs) in step.
        static::saved(fn (self $return) => $return->plateJob?->syncReturned());
        static::deleted(fn (self $return) => $return->plateJob?->syncReturned());
    }

    /**
     * @return BelongsTo<PlateJob, $this>
     */
    public function plateJob(): BelongsTo
    {
        return $this->belongsTo(PlateJob::class);
    }

    public function deletionLabel(): string
    {
        return __('Plates returned').' — '.$this->plates.', '.$this->returned_on?->format('j M Y').' ('.($this->plateJob?->processor?->name ?? '').')';
    }
}
