<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Enums\WorkOrderStage;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlateJobController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['unpaid', 'paid', 'all'], true) ? $request->query('show') : 'unpaid';

        return view('plate-jobs.index', [
            'jobs' => PlateJob::query()->with(['processor', 'workOrder'])
                ->when($show === 'unpaid', fn ($q) => $q->whereNull('paid_on'))
                ->when($show === 'paid', fn ($q) => $q->whereNotNull('paid_on'))
                ->latest('job_on')->latest('id')->paginate(30)->withQueryString(),
            'unpaidTotal' => (int) PlateJob::query()->whereNull('paid_on')->sum('amount'),
            'monthPlates' => (int) PlateJob::query()->where('job_on', '>=', now()->startOfMonth())->sum('plates'),
            'monthAmount' => (int) PlateJob::query()->where('job_on', '>=', now()->startOfMonth())->sum('amount'),
            'show' => $show,
        ]);
    }

    public function create(Request $request): View
    {
        return view('plate-jobs.create', [
            'processors' => Party::query()->active()->where('kind', PartyKind::PlateProcessor)->orderBy('name')->get(),
            'orders' => WorkOrder::query()->with('bookFormat.book')->where('stage', '!=', WorkOrderStage::Dispatched)->latest('id')->get(),
            'orderId' => $request->integer('order') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'party_id' => ['required', Rule::exists('parties', 'id')->where('kind', PartyKind::PlateProcessor->value)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'job_on' => ['required', 'date'],
            'plates' => ['required', 'integer', 'min:1'],
            'rate' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        PlateJob::create($data + ['amount' => (int) round($data['plates'] * $data['rate']), 'created_by' => $request->user()->id]);

        return redirect()->route('plate-jobs.index')->with('status', __('Plate job saved.'));
    }

    /**
     * Clear the processor's bill.
     */
    public function pay(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['required', 'date'], 'payment_note' => ['nullable', 'string', 'max:255']]);
        $plateJob->update($data);

        return back()->with('status', __('Plate bill of Rs :n marked as paid.', ['n' => number_format($plateJob->amount)]));
    }
}
