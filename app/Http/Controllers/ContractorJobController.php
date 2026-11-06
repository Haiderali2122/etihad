<?php

namespace App\Http\Controllers;

use App\Enums\ContractorJobKind;
use App\Enums\WorkOrderStage;
use App\Http\Requests\StoreContractorJobRequest;
use App\Models\Contractor;
use App\Models\ContractorJob;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractorJobController extends Controller
{
    public function create(Request $request): View
    {
        $order = WorkOrder::query()->with('bookFormat')->find($request->integer('order'));

        return view('contractors.jobs.create', [
            'contractors' => Contractor::query()->active()->orderBy('name')->get(),
            'orders' => WorkOrder::query()->with('bookFormat.book')->whereNotIn('stage', [WorkOrderStage::Waiting, WorkOrderStage::Dispatched])->latest('id')->get(),
            'order' => $order,
            'kind' => ContractorJobKind::tryFrom((string) $request->query('kind'))
                ?? ($order?->stage === WorkOrderStage::Binding ? ContractorJobKind::Binding : ContractorJobKind::Folding),
            'contractorId' => $request->integer('contractor') ?: null,
        ]);
    }

    public function store(StoreContractorJobRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $kind = ContractorJobKind::from($data['kind']);

        $job = ContractorJob::create($data + [
            'amount' => ContractorJob::price($kind, (float) $data['forms'], (int) $data['copies'],
                (float) ($data['folding_rate'] ?? 0), (float) ($data['form_rate'] ?? 0), (float) ($data['copy_rate'] ?? 0)),
            'state' => 'in_progress',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route($job->work_order_id ? 'work-orders.show' : 'contractors.show', $job->work_order_id ?? $job->contractor_id)
            ->with('status', __(':kind job given to :name: Rs :amount.', ['kind' => $kind->label(), 'name' => $job->contractor->name, 'amount' => number_format($job->amount)]));
    }

    /**
     * Finish a job: asks the date it was completed, which cannot be before it was given out.
     */
    public function done(Request $request, ContractorJob $contractorJob): RedirectResponse
    {
        abort_if($contractorJob->isDone(), 409, __('This job is already done.'));

        $data = $request->validate([
            'done_on' => ['required', 'date', 'after_or_equal:'.$contractorJob->job_on->toDateString(), 'before_or_equal:today'],
        ]);

        $contractorJob->update(['state' => 'done', 'done_on' => $data['done_on']]);

        return back()->with('status', __('Job marked as done.'));
    }

    public function edit(ContractorJob $contractorJob): View
    {
        return view('contractors.jobs.edit', ['job' => $contractorJob->load(['contractor', 'workOrder'])]);
    }

    /**
     * Forms, copies, rates and date can be corrected; the amount is worked out again.
     */
    public function update(Request $request, ContractorJob $contractorJob): RedirectResponse
    {
        $binding = $contractorJob->kind === ContractorJobKind::Binding;
        $data = $request->validate([
            'job_on' => ['required', 'date', 'before_or_equal:today'],
            'forms' => ['required', 'numeric', 'min:0.01', 'max:9999', 'decimal:0,2'],
            'copies' => ['required', 'integer', 'min:1'],
            'folding_rate' => [$binding ? 'nullable' : 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'form_rate' => [$binding ? 'required' : 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'copy_rate' => [$binding ? 'required' : 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $contractorJob->update($data + [
            'amount' => ContractorJob::price($contractorJob->kind, (float) $data['forms'], (int) $data['copies'],
                (float) ($data['folding_rate'] ?? 0), (float) ($data['form_rate'] ?? 0), (float) ($data['copy_rate'] ?? 0)),
        ]);

        return redirect()->route('contractors.show', $contractorJob->contractor_id)->with('status', __('Job saved: Rs :amount.', ['amount' => number_format($contractorJob->amount)]));
    }

    public function destroy(ContractorJob $contractorJob): RedirectResponse
    {
        $contractorId = $contractorJob->contractor_id;
        $contractorJob->delete();

        return redirect()->route('contractors.show', $contractorId)->with('status', __('Job deleted.'));
    }
}
