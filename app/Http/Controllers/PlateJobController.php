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
            'back' => $request->integer('order') ? route('work-orders.show', $request->integer('order')) : route('plate-jobs.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $job = PlateJob::create($data + ['amount' => (int) round($data['plates'] * $data['rate']), 'created_by' => $request->user()->id]);

        return redirect($job->work_order_id ? route('work-orders.show', $job->work_order_id) : route('plate-jobs.index'))->with('status', __('Plate job saved.'));
    }

    /**
     * The same plate record is edited from the Plates and Processors tabs, so a change shows in both.
     */
    public function edit(Request $request, PlateJob $plateJob): View
    {
        return view('plate-jobs.create', [
            'job' => $plateJob,
            'processors' => Party::query()->where('kind', PartyKind::PlateProcessor)->where(fn ($q) => $q->where('is_active', true)->orWhereKey($plateJob->party_id))->orderBy('name')->get(),
            'orders' => WorkOrder::query()->with('bookFormat.book')->where(fn ($q) => $q->where('stage', '!=', WorkOrderStage::Dispatched)->orWhereKey($plateJob->work_order_id))->latest('id')->get(),
            'orderId' => $plateJob->work_order_id,
            'back' => $this->safeReturnUrl($request->query('return')) ?? route('plate-jobs.index', ['show' => 'all']),
        ]);
    }

    public function update(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'returned_plates' => ['required', 'integer', 'min:0', 'lte:plates'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $plateJob->update($data + ['amount' => (int) round($data['plates'] * $data['rate'])]);

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? route('plate-jobs.index', ['show' => 'all']))->with('status', __('Plate job saved.'));
    }

    /**
     * Removes the plate record from the Plates and Processors tabs, and its payment from Cash-out.
     */
    public function destroy(PlateJob $plateJob): RedirectResponse
    {
        $plateJob->load(['processor', 'workOrder'])->delete();

        return back()->with('status', __('Plate job deleted.'));
    }

    /**
     * Plates the processor sent back.
     */
    public function returnPlates(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $data = $request->validate([
            'returned_plates' => ['required', 'integer', 'min:0', 'max:'.$plateJob->plates],
            'returned_on' => ['required', 'date', 'after_or_equal:'.$plateJob->job_on->toDateString(), 'before_or_equal:today'],
        ]);
        $plateJob->update($data);

        return back()->with('status', __(':n plates returned; :held still with :name.', ['n' => $plateJob->returned_plates, 'held' => $plateJob->platesHeld(), 'name' => $plateJob->processor->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'party_id' => ['required', Rule::exists('parties', 'id')->where('kind', PartyKind::PlateProcessor->value)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'job_on' => ['required', 'date'],
            'plates' => ['required', 'integer', 'min:1'],
            'rate' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Clear the processor's bill.
     */
    public function pay(Request $request, PlateJob $plateJob): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['required', 'date', 'after_or_equal:'.$plateJob->job_on->toDateString(), 'before_or_equal:today'], 'payment_note' => ['nullable', 'string', 'max:255']]);
        $plateJob->update($data);

        return back()->with('status', __('Plate bill of Rs :n marked as paid.', ['n' => number_format($plateJob->amount)]));
    }
}
