<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ForemanCash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Foreman cash: each time the manager hands a foreman money, in any amount on any day. A month shows its two
 * periods with their totals: 10th–21st (counted on the 22nd's advance salary screen) and 22nd–9th of next month
 * (counted on the 10th's salaries screen).
 */
class ForemanCashController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->month($request->query('month'));
        $rows = ForemanCash::query()->with(['foreman', 'cashOutExpense'])->whereDate('month', $month->toDateString())
            ->orderBy('given_on')->orderBy('id')->get();

        return view('foreman-cash.index', [
            'month' => $month,
            'periods' => collect([ForemanCash::Advance, ForemanCash::Final])->mapWithKeys(fn (string $p) => [$p => [
                'window' => ForemanCash::window($month, $p),
                'rows' => $rows->where('payday', $p)->values(),
            ]]),
            'foremen' => Employee::query()->active()->where('is_foreman', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $cash = ForemanCash::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('foreman-cash.index', ['month' => $cash->month->format('Y-m')])
            ->with('status', __('Rs :n given to :name on :date.', ['n' => number_format($cash->amount), 'name' => $cash->foreman->name, 'date' => $cash->given_on->format('j M')]));
    }

    public function edit(ForemanCash $foremanCash): View
    {
        return view('foreman-cash.edit', [
            'cash' => $foremanCash,
            'foremen' => Employee::query()->where('is_foreman', true)->where(fn ($q) => $q->where('is_active', true)->orWhereKey($foremanCash->foreman_id))->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ForemanCash $foremanCash): RedirectResponse
    {
        $foremanCash->update($this->validated($request));

        return redirect()->route('foreman-cash.index', ['month' => $foremanCash->month->format('Y-m')])->with('status', __('Saved.'));
    }

    /**
     * @return array{foreman_id: int, given_on: string, amount: int, note: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'foreman_id' => ['required', Rule::exists('employees', 'id')->where('is_foreman', true)],
            'given_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function month(mixed $value): Carbon
    {
        try {
            if (is_string($value) && $value !== '') {
                return Carbon::createFromFormat('!Y-m', $value)->startOfMonth();
            }
        } catch (\Throwable) {
            // fall through to the default
        }

        // The month whose paydays are coming up: from the 10th on it is this month, before that last month.
        return ForemanCash::periodFor(today())['month'];
    }
}
