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
        ]);
    }

    /**
     * Entries by someone who can approve are approved on save; everyone else's wait.
     */
    public static function bootRequiresApproval(): void
    {
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
