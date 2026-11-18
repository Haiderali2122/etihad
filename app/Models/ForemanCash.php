<?php

namespace App\Models;

use App\Models\Concerns\RecordsCashOut;
use App\Models\Concerns\RecordsDeletion;
use Database\Factories\ForemanCashFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One handout of cash from the manager to a foreman, who gives it to his team as kharcha. The manager gives it in
 * parts on any day; the date decides which payday it counts toward:
 *  - 10th–21st of a month: that month's advance salary on the 22nd;
 *  - 22nd to the end of the month, and 1st–9th of the next: that month's salaries, paid on the 10th.
 * Each handout comes off the manager's cash in Cash-out.
 */
#[Fillable(['foreman_id', 'month', 'payday', 'given_on', 'amount', 'note', 'created_by'])]
class ForemanCash extends Model
{
    /** @use HasFactory<ForemanCashFactory> */
    use HasFactory, RecordsCashOut, RecordsDeletion;

    public const Advance = 'advance';

    public const Final = 'final';

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'given_on' => 'date',
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The payday a handout counts toward always follows its date.
        static::saving(function (self $cash): void {
            ['month' => $month, 'payday' => $payday] = self::periodFor($cash->given_on);
            $cash->month = $month;
            $cash->payday = $payday;
        });
    }

    /**
     * The salary month and payday a handout given on this date counts toward.
     *
     * @return array{month: Carbon, payday: string}
     */
    public static function periodFor(mixed $date): array
    {
        $date = Carbon::parse($date);

        return match (true) {
            $date->day >= 10 && $date->day <= 21 => ['month' => $date->copy()->startOfMonth(), 'payday' => self::Advance],
            $date->day >= 22 => ['month' => $date->copy()->startOfMonth(), 'payday' => self::Final],
            default => ['month' => $date->copy()->subMonthNoOverflow()->startOfMonth(), 'payday' => self::Final],
        };
    }

    /**
     * The dates a payday's handouts fall between, for showing on screen.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public static function window(Carbon $month, string $payday): array
    {
        $month = $month->copy()->startOfMonth();

        return $payday === self::Advance
            ? ['from' => $month->copy()->day(10), 'to' => $month->copy()->day(21)]
            : ['from' => $month->copy()->day(22), 'to' => $month->copy()->addMonthNoOverflow()->day(9)];
    }

    /**
     * Total each foreman was given toward one payday.
     *
     * @return array<int, int> foreman id => total
     */
    public static function totals(Carbon $month, string $payday): array
    {
        return self::query()->where('payday', $payday)->whereDate('month', $month->copy()->startOfMonth()->toDateString())
            ->selectRaw('foreman_id, SUM(amount) as total')->groupBy('foreman_id')->pluck('total', 'foreman_id')
            ->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function foreman(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'foreman_id');
    }

    public function cashOut(): ?array
    {
        return [
            'amount' => (int) $this->amount,
            'date' => $this->given_on,
            'paid_to' => (string) $this->foreman?->name,
            'reason' => 'Cash to foreman for '.($this->payday === self::Advance ? 'advance salaries' : 'salaries').' '.$this->month->format('F Y').($this->note ? ', '.$this->note : ''),
            'head' => $this->payday === self::Advance ? 'Salary advances' : 'Salaries',
        ];
    }

    public function cashOutUrl(): string
    {
        return route('foreman-cash.index', ['month' => $this->month->format('Y-m')]);
    }

    public function deletionLabel(): string
    {
        return __('Cash to :name', ['name' => $this->foreman?->name]).' — Rs '.number_format($this->amount).', '.$this->given_on?->format('j M Y');
    }

    /**
     * Salary money always comes off the manager's cash, whoever enters it.
     */
    protected function cashOutPayer(): ?int
    {
        return $this->managerWithCash();
    }
}
