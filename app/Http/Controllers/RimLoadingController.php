<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Enums\RimMove;
use App\Enums\WorkOrderStage;
use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\RimLoading;
use App\Models\WorkOrder;
use App\Models\YounusPayment;
use App\Support\OtherOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Younus ka khata: all the work his labourers do (rims into the godown, to and from the presses, to folding, truck
 * loading, anything typed), entered here as written, quantity × rate. He gets advances during the week and the rest
 * on Saturday; each payment comes off the manager's cash.
 */
class RimLoadingController extends Controller
{
    /**
     * The khata for one week (Sunday to Saturday): the work entered, advances given, what is left to pay, and the
     * form to add work.
     */
    public function index(Request $request): View
    {
        $week = YounusPayment::weekStart(rescue(fn () => Carbon::parse((string) $request->query('week', today()->toDateString())), today(), false));
        $weekEnd = $week->copy()->addDays(6);
        $inWeek = fn (string $column) => fn ($q) => $q->whereDate($column, '>=', $week->toDateString())->whereDate($column, '<=', $weekEnd->toDateString());

        // Everything ever billed (lines not paid one by one, the old way) less everything given to him.
        $billed = (int) RimLoading::query()->whereNull('paid_on')->sum('amount');
        $given = (int) YounusPayment::query()->sum('amount');

        $orders = WorkOrder::query()->with('bookFormat.book')->where('stage', '!=', WorkOrderStage::Dispatched)->latest('id')->limit(200)->get();
        $prefill = WorkOrder::query()->find($request->integer('order'));

        return view('rim-loadings.index', [
            'week' => $week,
            'weekEnd' => $weekEnd,
            'lines' => RimLoading::query()->with(['workOrder.bookFormat.book', 'paperReceipt.paperType', 'paperReceipt.party'])->tap($inWeek('loaded_on'))
                ->latest('loaded_on')->latest('id')->get(),
            'payments' => YounusPayment::query()->tap($inWeek('paid_on'))->orderBy('paid_on')->orderBy('id')->get(),
            'weeks' => $this->recentWeeks($week),
            // The week receipt is given once he has been paid for the week.
            'weekPaid' => YounusPayment::query()->where('kind', YounusPayment::Settlement)->tap($inWeek('paid_on'))->exists(),
            'weekBill' => (int) RimLoading::query()->tap($inWeek('loaded_on'))->sum('amount'),
            'weekAdvances' => (int) YounusPayment::query()->where('kind', YounusPayment::Advance)->tap($inWeek('paid_on'))->sum('amount'),
            'toPay' => $billed - $given,
            'orders' => $orders->when($prefill && ! $orders->contains('id', $prefill->id), fn ($c) => $c->prepend($prefill)),
            'prefill' => $prefill,
            'prefillKind' => RimMove::tryFrom((string) $request->query('kind')),
            'workTypes' => collect(RimMove::cases())->map(fn (RimMove $k) => $k->label())
                ->merge(RimLoading::query()->whereNotNull('work_type')->distinct()->pluck('work_type'))->unique()->values(),
            'units' => collect(['rims', 'bundles', 'books', 'trips', 'packets'])->merge(RimLoading::query()->distinct()->pluck('unit'))->filter()->unique()->values(),
            'lastRates' => $this->lastRates(),
            'paperTypes' => PaperType::query()->active()->orderBy('code')->get(),
            'parties' => Party::query()->active()->orderBy('name')->pluck('name'),
            'godowns' => Godown::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Younus's work, one entry or several at once: each written freely (work picked or typed, details, an order
     * when it belongs to one), with a quantity, unit and rate. Rims brought into the godown also go into that
     * paper's stock; loading an order to folding finishes that step of the order.
     */
    public function storeLine(Request $request): RedirectResponse
    {
        $fields = ['work_type', 'details', 'work_order', 'quantity', 'unit', 'rate', 'paper_type_id', 'party_name', 'godown_id'];
        $rows = is_array($request->input('rows')) ? $request->input('rows') : [$request->only($fields)];
        $into = RimMove::IntoGodown->label();

        // The empty entry the form always shows at the end is dropped; a known work's name is matched however typed.
        $rows = collect($rows)->filter(fn ($row) => is_array($row) && collect(['work_type', 'details', 'work_order', 'quantity', 'rate'])->contains(fn ($f) => trim((string) ($row[$f] ?? '')) !== ''))
            ->map(function (array $row): array {
                $typed = trim((string) preg_replace('/\s+/u', ' ', (string) ($row['work_type'] ?? '')));
                $known = collect(RimMove::cases())->first(fn (RimMove $k) => mb_strtolower($k->label()) === mb_strtolower($typed));

                return [
                    ...$row,
                    'work_type' => $known?->label() ?? $typed,
                    'work_order_id' => $this->orderFrom((string) ($row['work_order'] ?? ''))?->id,
                    'unit' => trim((string) ($row['unit'] ?? '')) ?: 'rims',
                    'party_name' => trim((string) ($row['party_name'] ?? '')),
                ];
            })->values()->all();
        $request->merge(['rows' => $rows]);

        $data = $request->validate([
            'loaded_on' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.work_type' => ['nullable', 'string', 'max:80'],
            'rows.*.details' => ['nullable', 'string', 'max:1000'],
            'rows.*.work_order' => ['nullable', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                $i = (int) explode('.', $attribute)[1];
                if (filled($value) && ! $request->input("rows.{$i}.work_order_id")) {
                    $fail(__('No work order :n was found.', ['n' => $value]));
                }
            }],
            'rows.*.work_order_id' => ['nullable', 'integer'],
            'rows.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'rows.*.unit' => ['required', 'string', 'max:20'],
            'rows.*.rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'rows.*.paper_type_id' => ['nullable', 'required_if:rows.*.work_type,'.$into, 'exists:paper_types,id'],
            'rows.*.party_name' => ['nullable', 'required_if:rows.*.work_type,'.$into, 'string', 'max:120'],
            'rows.*.godown_id' => ['nullable', 'required_if:rows.*.work_type,'.$into, 'exists:godowns,id'],
        ], [
            'rows.required' => __('Write at least one entry.'),
            'rows.*.paper_type_id.required_if' => __('Pick the paper for rims brought into the godown.'),
            'rows.*.party_name.required_if' => __('Write whose paper it is for rims brought into the godown.'),
            'rows.*.godown_id.required_if' => __('Pick where the rims were unloaded.'),
        ], [
            'rows.*.quantity' => __('quantity'), 'rows.*.rate' => __('rate'), 'rows.*.unit' => __('unit'),
            'rows.*.paper_type_id' => __('paper'), 'rows.*.party_name' => __('whose paper'), 'rows.*.godown_id' => __('godown'),
        ]);

        $said = [];
        $lines = DB::transaction(function () use ($data, $request, &$said): array {
            $lines = [];
            foreach ($data['rows'] as $row) {
                [$line, $notes] = $this->addLine($row, $data['loaded_on'], $request->user()->id);
                $lines[] = $line;
                $said = [...$said, ...$notes];
            }

            return $lines;
        });

        $total = collect($lines)->sum('amount');
        $head = count($lines) === 1
            ? __(':work: Rs :amount added to Younus ka khata.', ['work' => $lines[0]->workLabel(), 'amount' => number_format($total)])
            : __(':n entries, Rs :amount, added to Younus ka khata.', ['n' => count($lines), 'amount' => number_format($total)]);

        return redirect()->route('rim-loadings.index', ['week' => YounusPayment::weekStart($data['loaded_on'])->toDateString()])
            ->with('status', trim($head.' '.implode(' ', $said)));
    }

    /**
     * One khata line, and what it did besides: rims into stock, the order it belongs to, the step it finished.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: RimLoading, 1: list<string>}
     */
    private function addLine(array $row, string $on, int $by): array
    {
        $kind = collect(RimMove::cases())->first(fn (RimMove $k) => $k->label() === $row['work_type']) ?? RimMove::Other;
        $order = $row['work_order_id'] ? WorkOrder::query()->find($row['work_order_id']) : null;
        $said = [];

        $receipt = null;
        if ($kind === RimMove::IntoGodown) {
            // Rims Younus brought in from outside go into that party's stock of that paper.
            $party = OtherOption::findOrCreate(Party::class, $row['party_name'], ['kind' => PartyKind::Supplier, 'is_active' => true]);
            $receipt = PaperReceipt::create([
                'slip_no' => 'YK-'.now()->format('ymdHis').'-'.random_int(100, 999), 'received_at' => Carbon::parse($on)->setTimeFrom(now()),
                'party_id' => $party->id, 'paper_type_id' => $row['paper_type_id'], 'quantity' => (int) round((float) $row['quantity']),
                'truck_no' => null, 'godown_id' => $row['godown_id'], 'received_by' => 'Younus',
                'note' => mb_substr(trim(__('Brought in by Younus').'. '.($row['details'] ?? '')), 0, 255), 'created_by' => $by,
            ]);
            $said[] = __(':n rims added to the :paper stock of :party in :godown.', [
                'n' => number_format($receipt->quantity), 'party' => $party->name, 'paper' => $receipt->paperType->label(), 'godown' => $receipt->godown?->name,
            ]);
        }

        $line = RimLoading::create([
            'work_order_id' => $order?->id, 'paper_receipt_id' => $receipt?->id, 'kind' => $kind,
            'work_type' => $kind === RimMove::Other && filled($row['work_type']) ? $row['work_type'] : null, 'details' => $row['details'] ?? null,
            'loaded_on' => $on, 'rims' => $row['quantity'], 'unit' => $row['unit'], 'rate_per_rim' => $row['rate'],
            'amount' => RimLoading::price((float) $row['quantity'], (float) $row['rate']), 'loaded_by' => 'Younus', 'created_by' => $by,
        ]);

        if ($order) {
            $said[] = __('Linked to :number (:book).', ['number' => $order->number, 'book' => $order->bookName()]);
            // Loading the printed rims to folding finishes the order's "Loading to folding" step.
            if (in_array($kind, [RimMove::ToFolding, RimMove::FromOutside], true) && $order->stage === WorkOrderStage::ToFolding && ! $order->advanceBlocker()) {
                $next = $order->stage->next();
                $order->forceFill(['stage' => $next])->save();
                $said[] = __(':number moved to :stage.', ['number' => $order->number, 'stage' => $next->label()]);
            }
        }

        return [$line, $said];
    }

    /**
     * The week's receipts for Younus: his work (every line of the week, however it was entered), his advances, or
     * the week as a whole: the bill, the advances and what remains.
     */
    public function receipt(Request $request): View
    {
        $week = YounusPayment::weekStart(rescue(fn () => Carbon::parse((string) $request->query('week', today()->toDateString())), today(), false));
        $weekEnd = $week->copy()->addDays(6);
        $kind = in_array($request->query('kind'), ['advance', 'week'], true) ? $request->query('kind') : 'work';
        $range = fn (string $column) => fn ($q) => $q->whereDate($column, '>=', $week->toDateString())->whereDate($column, '<=', $weekEnd->toDateString());
        $payments = YounusPayment::query()->tap($range('paid_on'))->orderBy('paid_on')->orderBy('id')->get();

        return view('rim-loadings.receipt', [
            'kind' => $kind,
            'week' => $week,
            'weekEnd' => $weekEnd,
            'lines' => RimLoading::query()->with('workOrder.bookFormat.book')->tap($range('loaded_on'))->orderBy('loaded_on')->orderBy('id')->get(),
            'advances' => $payments->where('kind', YounusPayment::Advance)->values(),
            'paidSaturday' => (int) $payments->where('kind', YounusPayment::Settlement)->sum('amount'),
        ]);
    }

    /**
     * Money given to Younus during the week.
     */
    public function advance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $payment = YounusPayment::create($data + ['kind' => YounusPayment::Advance, 'created_by' => $request->user()->id]);

        return redirect()->route('rim-loadings.index', ['week' => YounusPayment::weekStart($payment->paid_on)->toDateString()])
            ->with('status', __('Rs :n advance given to Younus.', ['n' => number_format($payment->amount)]));
    }

    /**
     * Saturday: the rest of what he is owed (his bills less his advances), or a part of it.
     */
    public function settle(Request $request): RedirectResponse
    {
        $owed = (int) RimLoading::query()->whereNull('paid_on')->sum('amount') - (int) YounusPayment::query()->sum('amount');
        $request->mergeIfMissing(['amount' => $owed]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $owed)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['amount.max' => __('Only Rs :n is owed to Younus.', ['n' => number_format(max(0, $owed))])]);
        $payment = YounusPayment::create($data + ['kind' => YounusPayment::Settlement, 'created_by' => $request->user()->id]);

        return redirect()->route('rim-loadings.index', ['week' => YounusPayment::weekStart($payment->paid_on)->toDateString()])
            ->with('status', __('Rs :n paid to Younus; Rs :left still owed.', ['n' => number_format($payment->amount), 'left' => number_format($owed - $payment->amount)]));
    }

    /**
     * Up to 8 weeks, ending with the one being viewed, newest first: each week's lines and totals.
     *
     * @return list<array{start: Carbon, end: Carbon, lines: Collection<int, RimLoading>, bill: int, advances: int, paid: int}>
     */
    private function recentWeeks(Carbon $last): array
    {
        $first = $last->copy()->subWeeks(7);
        $end = $last->copy()->addDays(6)->toDateString();
        $lines = RimLoading::query()->with(['workOrder.bookFormat.book', 'paperReceipt.paperType', 'paperReceipt.party'])
            ->whereDate('loaded_on', '>=', $first->toDateString())->whereDate('loaded_on', '<=', $end)
            ->latest('loaded_on')->latest('id')->get()->groupBy(fn (RimLoading $l) => YounusPayment::weekStart($l->loaded_on)->toDateString());
        $payments = YounusPayment::query()->whereDate('paid_on', '>=', $first->toDateString())->whereDate('paid_on', '<=', $end)->get()
            ->groupBy(fn (YounusPayment $p) => YounusPayment::weekStart($p->paid_on)->toDateString());

        $weeks = [];
        for ($start = $last->copy(); $start->gte($first); $start->subWeek()) {
            $key = $start->toDateString();
            $weekLines = $lines->get($key, collect());
            $weekPay = $payments->get($key, collect());
            if ($weekLines->isEmpty() && $weekPay->isEmpty()) {
                continue;
            }
            $weeks[] = [
                'start' => $start->copy(), 'end' => $start->copy()->addDays(6), 'lines' => $weekLines,
                'bill' => (int) $weekLines->sum('amount'),
                'advances' => (int) $weekPay->where('kind', YounusPayment::Advance)->sum('amount'),
                'paid' => (int) $weekPay->where('kind', YounusPayment::Settlement)->sum('amount'),
            ];
        }

        return $weeks;
    }

    /**
     * The order a typed or picked value names: "WO-1001", "WO-1001 · Book", or its id.
     */
    private function orderFrom(string $value): ?WorkOrder
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/\bWO-?\d+\b/i', $value, $m)) {
            return WorkOrder::query()->where('number', strtoupper(str_replace('WO', 'WO-', str_replace('-', '', $m[0]))))->first();
        }

        return ctype_digit($value) ? WorkOrder::query()->find((int) $value) : null;
    }

    /**
     * The last rate for each work and unit, keyed "work|unit" in lower case, filled in on the form.
     *
     * @return array<string, float>
     */
    private function lastRates(): array
    {
        return RimLoading::query()->orderBy('id')->get(['kind', 'work_type', 'unit', 'rate_per_rim'])
            ->mapWithKeys(fn (RimLoading $l) => [mb_strtolower($l->workLabel().'|'.$l->unit) => (float) $l->rate_per_rim])->all();
    }

    /**
     * Rims loaded to folding for an order by hand (in-house orders get this line automatically when printing is done).
     */
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($workOrder->paper_issue_id && ! in_array($workOrder->stage, [WorkOrderStage::Waiting, WorkOrderStage::Printing, WorkOrderStage::Dispatched], true), 409,
            __('Rims are loaded to folding once printing is done.'));

        $data = $request->validate([
            'loaded_on' => ['required', 'date', 'after_or_equal:'.$workOrder->ordered_on->toDateString(), 'before_or_equal:today'],
            'rims' => ['required', 'numeric', 'min:0.5'],
            'rate_per_rim' => ['required', 'numeric', 'min:0'],
            'loaded_by' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $loading = $workOrder->rimLoadings()->create($data + [
            // In-house: our press → folding; outsourced: outside press → back for folding.
            'kind' => $workOrder->mode === PrintMode::Outsource ? RimMove::FromOutside : RimMove::ToFolding,
            'loaded_by' => ($data['loaded_by'] ?? null) ?: 'Younus',
            'amount' => RimLoading::price((float) $data['rims'], (float) $data['rate_per_rim']),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __(':rims rims loaded to folding: Rs :amount to :name.', [
            'rims' => rtrim(rtrim(number_format((float) $loading->rims, 2), '0'), '.'), 'amount' => number_format($loading->amount), 'name' => $loading->loaded_by,
        ]));
    }

    /**
     * Change an unpaid line: its rims, rate per rim, date or note.
     */
    public function edit(RimLoading $rimLoading): View
    {
        abort_if($rimLoading->isPaid(), 409, __('A paid line cannot be changed.'));

        return view('rim-loadings.edit', ['line' => $rimLoading->load('workOrder.bookFormat.book')]);
    }

    public function update(Request $request, RimLoading $rimLoading): RedirectResponse
    {
        abort_if($rimLoading->isPaid(), 409, __('A paid line cannot be changed.'));

        $data = $request->validate([
            'kind' => ['required', Rule::enum(RimMove::class)],
            'loaded_on' => ['required', 'date', 'before_or_equal:today'],
            'rims' => ['required', 'numeric', 'min:0.5'],
            'rate_per_rim' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $rimLoading->update($data + ['amount' => RimLoading::price((float) $data['rims'], (float) $data['rate_per_rim'])]);

        return redirect()->route('rim-loadings.index')->with('status', __('Line saved: Rs :n.', ['n' => number_format($rimLoading->amount)]));
    }

    /**
     * Pay one line; it then shows in Cash-out.
     */
    public function pay(Request $request, RimLoading $rimLoading): RedirectResponse
    {
        abort_if($rimLoading->isPaid(), 409, __('This line is already paid.'));

        $data = $request->validate([
            'paid_on' => ['nullable', 'date', 'after_or_equal:'.$rimLoading->loaded_on->toDateString(), 'before_or_equal:today'],
        ]);
        $rimLoading->update(['paid_on' => $data['paid_on'] ?? today()->toDateString()]);

        return back()->with('status', __('Rs :amount paid to :name.', ['amount' => number_format($rimLoading->amount), 'name' => $rimLoading->loaded_by]));
    }

    /**
     * Pay every unpaid line at once, dated today.
     */
    public function payAll(): RedirectResponse
    {
        $lines = RimLoading::query()->whereNull('paid_on')->where('amount', '>', 0)->get();
        DB::transaction(fn () => $lines->each(fn (RimLoading $l) => $l->update(['paid_on' => today()->toDateString()])));

        return back()->with('status', __('Paid all :n lines to Younus: Rs :amount.', ['n' => $lines->count(), 'amount' => number_format($lines->sum('amount'))]));
    }

    /**
     * A line entered by mistake can be removed while it is unpaid.
     */
    public function destroy(RimLoading $rimLoading): RedirectResponse
    {
        abort_if($rimLoading->isPaid(), 409, __('A paid line cannot be deleted.'));
        $rimLoading->delete();

        return back()->with('status', __('Line deleted.'));
    }
}
