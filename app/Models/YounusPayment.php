<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money given to Younus: an advance during the week, or the rest of his bill on Saturday. It comes off the
 * manager's cash. His week runs Sunday to Saturday.
 */
#[Fillable(['kind', 'paid_on', 'amount', 'note', 'created_by'])]
class YounusPayment extends Model
{
    use RecordsCashOut, RecordsDeletion;

    public const Advance = 'advance';

    public const Settlement = 'settlement';

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * The Sunday his week starts on, for a date.
     */
    public static function weekStart(Carbon|string $date): Carbon
    {
        return Carbon::parse($date)->startOfWeek(Carbon::SUNDAY)->startOfDay();
    }

    public function isAdvance(): bool
    {
        return $this->kind === self::Advance;
    }

    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->paid_on,
            'paid_to' => 'Younus',
            'reason' => trim(($this->isAdvance() ? 'Younus advance' : 'Younus weekly payment').($this->note ? ', '.$this->note : '')),
            'head' => 'Loading labour',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('rim-loadings.index', ['week' => static::weekStart($this->paid_on)->toDateString()]);
    }

    public function cashOutSource(): string
    {
        return $this->isAdvance() ? __('Younus advance') : __('Younus weekly payment');
    }

    /**
     * Comes off the manager's cash, whoever enters it.
     */
    protected function cashOutPayer(): ?int
    {
        return $this->managerWithCash();
    }

    public function deletionLabel(): string
    {
        return $this->cashOutSource().' Rs '.number_format($this->amount).', '.$this->paid_on?->format('j M Y');
    }
}
