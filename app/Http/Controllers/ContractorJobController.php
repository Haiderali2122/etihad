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

    public function done(ContractorJob $contractorJob): RedirectResponse
    {
        $contractorJob->update(['state' => 'done']);

        return back()->with('status', __('Job marked as done.'));
    }
}
