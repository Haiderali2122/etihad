<?php

namespace App\Http\Controllers;

use App\Enums\ContractorPaymentKind;
use App\Enums\WorkOrderStage;
use App\Http\Requests\ContractorRequest;
use App\Models\Contractor;
use App\Models\ContractorPayment;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractorController extends Controller
{
    public function index(): View
    {
        $contractors = Contractor::query()
            ->withSum('jobs', 'amount')
            ->withSum(['payments' => fn ($q) => $q->notRejected()], 'amount')
            ->withCount(['jobs as open_jobs' => fn ($q) => $q->where('state', 'in_progress')])
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        return view('contractors.index', [
            'contractors' => $contractors,
            'owed' => $contractors->sum(fn ($c) => max(0, (int) $c->jobs_sum_amount - (int) $c->payments_sum_amount)),
            'advanced' => $contractors->sum(fn ($c) => max(0, (int) $c->payments_sum_amount - (int) $c->jobs_sum_amount)),
        ]);
    }

    public function create(): View
    {
        return view('contractors.form', ['contractor' => new Contractor(['is_active' => true, 'work' => 'both'])]);
    }

    public function store(ContractorRequest $request): RedirectResponse
    {
        $contractor = Contractor::create($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('contractors.show', $contractor)->with('status', __('Contractor :name saved.', ['name' => $contractor->name]));
    }

    /**
     * The contractor's hisab: every job earned and every advance or payment made, overall and per order.
     */
    public function show(Contractor $contractor): View
    {
        $jobs = $contractor->jobs()->with('workOrder.bookFormat.book')->latest('job_on')->latest('id')->get();
        $payments = $contractor->payments()->with('workOrder')->latest('paid_on')->latest('id')->get();
        $counted = $payments->where('status.value', '!=', 'rejected');

        $byOrder = $jobs->groupBy('work_order_id')->map(fn ($rows, $orderId) => [
            'order' => $rows->first()->workOrder,
            'earned' => (int) $rows->sum('amount'),
            'paid' => (int) $counted->where('work_order_id', $orderId ?: null)->sum('amount'),
        ]);

        return view('contractors.show', [
            'contractor' => $contractor,
            'jobs' => $jobs,
            'payments' => $payments,
            'earned' => (int) $jobs->sum('amount'),
            'paid' => (int) $counted->sum('amount'),
            'byOrder' => $byOrder,
            'orders' => WorkOrder::query()->where('stage', '!=', WorkOrderStage::Waiting)->latest('id')->take(50)->get(),
        ]);
    }

    public function edit(Contractor $contractor): View
    {
        return view('contractors.form', ['contractor' => $contractor]);
    }

    public function update(ContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('contractors.show', $contractor)->with('status', __('Contractor :name saved.', ['name' => $contractor->name]));
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        if ($contractor->jobs()->exists() || $contractor->payments()->exists()) {
            return back()->withErrors(['contractor' => __(':name has jobs or payments. Deactivate them instead so the hisab stays.', ['name' => $contractor->name])]);
        }

        $contractor->delete();

        return redirect()->route('contractors.index')->with('status', __('Contractor :name deleted.', ['name' => $contractor->name]));
    }

    /**
     * An advance before the work, or a payment to settle the hisab.
     */
    public function pay(Request $request, Contractor $contractor): RedirectResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'kind' => ['required', Rule::enum(ContractorPaymentKind::class)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = ContractorPayment::create($data + ['contractor_id' => $contractor->id, 'created_by' => $request->user()->id]);

        return back()->with('status', $payment->isPending()
            ? __('Rs :n recorded and sent for approval.', ['n' => number_format($payment->amount)])
            : __('Rs :n recorded.', ['n' => number_format($payment->amount)]));
    }
}
