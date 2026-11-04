<?php

namespace App\Models\Concerns;

use App\Models\Deletion;

/**
 * Writes an entry to the deletion log whenever one of these records is deleted, keeping the reason the
 * person gave (from the delete dialog) and who did it. The log stays even after the record is gone.
 *
 * A model may set a friendlier `deletionKind()` or `deletionLabel()`.
 */
trait RecordsDeletion
{
    public static function bootRecordsDeletion(): void
    {
        static::deleted(function (self $model): void {
            $user = auth()->user();

            Deletion::create([
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'kind' => $model->deletionKind(),
                'label' => mb_substr($model->deletionLabel(), 0, 255),
                'note' => request('delete_note') ? mb_substr((string) request('delete_note'), 0, 255) : null,
                'deleted_by' => $user?->getKey(),
                'deleted_by_name' => $user?->name,
            ]);
        });
    }

    /**
     * A short word for the kind of record, shown in the log (e.g. "Book", "Work order").
     */
    public function deletionKind(): string
    {
        return __(ucfirst(mb_strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($this))))));
    }

    /**
     * A readable name for this record, shown in the log.
     */
    public function deletionLabel(): string
    {
        foreach (['name', 'number', 'code', 'title', 'slip_no'] as $attribute) {
            if (filled($this->getAttribute($attribute))) {
                return (string) $this->getAttribute($attribute);
            }
        }

        return $this->deletionKind().' #'.$this->getKey();
    }
}
