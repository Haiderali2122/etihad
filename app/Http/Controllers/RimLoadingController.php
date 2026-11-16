<?php

namespace App\Http\Controllers;

use App\Enums\PrintMode;
use App\Enums\RimMove;
use App\Enums\WorkOrderStage;
use App\Models\RimLoading;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Younus ka khata: every rim movement his labourers make (godown → our press, our press → folding, godown → outside
 * press, outside press → back) and the outside printing charge he takes to the press, each paid per rim. Lines are
 * added automatically from the work orders; each can be paid on its own, or all unpaid lines at once.
 */
class RimLoadingController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['unpaid', 'paid', 'all'], true) ? $request->query('show') : 'unpaid';
        $kind = RimMove::tryFrom((string) $request->query('kind'));
        $filtered = fn ($q) => $q->when($kind, fn ($q) => $q->where('kind', $kind));

        return view('rim-loadings.index', [
            'loadings' => RimLoading::query()->with('workOrder.bookFormat.book')->tap($filtered)
                ->when($show === 'unpaid', fn ($q) => $q->whereNull('paid_on'))
                ->when($show === 'paid', fn ($q) => $q->whereNotNull('paid_on'))
                ->latest('loaded_on')->latest('id')->paginate(40)->withQueryString(),
            'owed' => (int) RimLoading::query()->whereNull('paid_on')->sum('amount'),
            'unpaidCount' => RimLoading::query()->whereNull('paid_on')->where('amount', '>', 0)->count(),
            'paidThisMonth' => (int) RimLoading::query()->where('paid_on', '>=', now()->startOfMonth())->sum('amount'),
            'rims' => (float) RimLoading::query()->where('kind', '!=', RimMove::Printing)->where('loaded_on', '>=', now()->startOfMonth())->sum('rims'),
            'show' => $show,
            'kind' => $kind,
        ]);
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
