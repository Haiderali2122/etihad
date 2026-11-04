<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderStage;
use App\Models\RimLoading;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Younus ka khata: printed rims his labourers carry from the press to folding, paid per rim.
 */
class RimLoadingController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['unpaid', 'paid', 'all'], true) ? $request->query('show') : 'unpaid';

        return view('rim-loadings.index', [
            'loadings' => RimLoading::query()->with('workOrder.bookFormat.book')
                ->when($show === 'unpaid', fn ($q) => $q->whereNull('paid_on'))
                ->when($show === 'paid', fn ($q) => $q->whereNotNull('paid_on'))
                ->latest('loaded_on')->latest('id')->paginate(30)->withQueryString(),
            'earned' => (int) RimLoading::query()->sum('amount'),
            'paid' => (int) RimLoading::query()->whereNotNull('paid_on')->sum('amount'),
            'rims' => (float) RimLoading::query()->where('loaded_on', '>=', now()->startOfMonth())->sum('rims'),
            'show' => $show,
        ]);
    }

    /**
     * Rims loaded to folding for an order, once printing is done.
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
            'loaded_by' => ($data['loaded_by'] ?? null) ?: 'Younus',
            'amount' => RimLoading::price((float) $data['rims'], (float) $data['rate_per_rim']),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __(':rims rims loaded to folding: Rs :amount to :name.', [
            'rims' => rtrim(rtrim(number_format((float) $loading->rims, 2), '0'), '.'), 'amount' => number_format($loading->amount), 'name' => $loading->loaded_by,
        ]));
    }

    /**
     * Pay the loading labour; it then shows in Cash-out under Loading labour.
     */
    public function pay(Request $request, RimLoading $rimLoading): RedirectResponse
    {
        abort_if($rimLoading->isPaid(), 409, __('This loading is already paid.'));

        $data = $request->validate([
            'paid_on' => ['required', 'date', 'after_or_equal:'.$rimLoading->loaded_on->toDateString(), 'before_or_equal:today'],
        ]);
        $rimLoading->update($data);

        return back()->with('status', __('Rs :amount paid to :name.', ['amount' => number_format($rimLoading->amount), 'name' => $rimLoading->loaded_by]));
    }

    /**
     * A loading entered by mistake can be removed while it is unpaid.
     */
    public function destroy(RimLoading $rimLoading): RedirectResponse
    {
        abort_if($rimLoading->isPaid(), 409, __('A paid loading cannot be deleted.'));
        $rimLoading->delete();

        return back()->with('status', __('Loading deleted.'));
    }
}
