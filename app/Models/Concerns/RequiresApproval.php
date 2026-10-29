<?php

namespace App\Models\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour for entries the manager makes and the owner approves:
 * paper receipts, purchases, expenses and machine repairs.
 *
 * @property ApprovalStatus $status
 */
trait RequiresApproval
{
    public function initializeRequiresApproval(): void
    {
        $this->mergeCasts([
            'status' => ApprovalStatus::class,
            'reviewed_at' => 'datetime',
            'resent_at' => 'datetime',
        ]);
    }

    /**
     * Entries by someone who can approve are approved on save; everyone else's wait.
     */
    public static function bootRequiresApproval(): void
    {
        // Saving a corrected copy of an entry the owner sent back marks the old one as resent.
        static::creating(function (self $model): void {
            if ($original = static::beingResent()) {
                $original->releaseForResend();
            }
        });
        static::created(function (self $model): void {
            if ($original = static::beingResent()) {
                $original->forceFill(['resent_at' => now()])->saveQuietly();
                session()->forget('resending');
            }
        });

        static::creating(function (self $model): void {
            $creator = $model->created_by ? User::find($model->created_by) : null;

            if (! $model->isDirty('status')) {
                $model->status = $creator?->can('approve') ? ApprovalStatus::Approved : ApprovalStatus::Pending;
            }

            if ($model->status === ApprovalStatus::Approved && $creator?->can('approve') && ! $model->reviewed_by) {
                $model->reviewed_by = $creator->id;
                $model->reviewed_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Approved);
    }

    public function scopeNotRejected(Builder $query): void
    {
        $query->where('status', '!=', ApprovalStatus::Rejected);
    }

    /**
     * Sent back by the owner and not yet corrected and entered again.
     */
    public function scopeSentBackOpen(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Rejected)->whereNull('resent_at');
    }

    /**
     * The sent-back entry of this kind whose corrected copy is being entered in this session, if any.
     */
    public static function beingResent(): ?static
    {
        $resending = app()->bound('session') ? session('resending') : null;

        return is_array($resending) && ($resending['class'] ?? null) === static::class
            ? static::query()->sentBackOpen()->find($resending['id'] ?? 0)
            : null;
    }

    /**
     * The form values to start the corrected copy from. Kinds whose form differs override this.
     *
     * @return array<string, mixed>
     */
    public function resendInput(): array
    {
        return $this->baseResendInput();
    }

    /**
     * The entry's own fields, with dates and enums as the form expects them.
     *
     * @return array<string, mixed>
     */
    public function baseResendInput(): array
    {
        return collect($this->getFillable())
            ->reject(fn (string $field) => in_array($field, ['created_by', 'status', 'attachment'], true))
            ->mapWithKeys(fn (string $field) => [$field => match (true) {
                $this->{$field} instanceof \BackedEnum => $this->{$field}->value,
                $this->{$field} instanceof \DateTimeInterface => $this->{$field}->format('Y-m-d'),
                default => $this->{$field},
            }])
            ->all();
    }

    /**
     * Free anything unique the corrected copy needs to reuse, e.g. a paper receipt's slip number.
     */
    public function releaseForResend(): void
    {
        //
    }

    /**
     * Where the corrected copy is entered: normally this kind's own "add" form.
     */
    abstract public function resendUrl(): string;

    public function approve(User $reviewer, ?string $note = null): void
    {
        $this->review($reviewer, ApprovalStatus::Approved, $note);
    }

    public function reject(User $reviewer, ?string $note = null): void
    {
        $this->review($reviewer, ApprovalStatus::Rejected, $note);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    private function review(User $reviewer, ApprovalStatus $status, ?string $note): void
    {
        $this->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();
    }

    /**
     * Short label for the approvals queue, e.g. "Paper receipt".
     */
    abstract public function approvalKind(): string;

    abstract public function approvalTitle(): string;

    abstract public function approvalDetail(): string;

    abstract public function approvalAmount(): ?int;

    abstract public function approvalIcon(): string;

    abstract public function approvalUrl(): string;
}
