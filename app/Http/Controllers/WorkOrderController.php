<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Enums\PrintMode;
use App\Enums\WorkOrderStage;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Models\Book;
use App\Models\BookFormat;
use App\Models\Godown;
use App\Models\Machine;
use App\Models\OutsidePrintReceipt;
use App\Models\PaperReceipt;
use App\Models\Party;
use App\Models\Setting;
use App\Models\WorkOrder;
use App\Support\PaperStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    /**
     * Orders on a board, one column per stage. Dispatched orders show for the last 30 days.
     */
    public function index(Request $request): View
    {
        $orders = WorkOrder::query()->with(['bookFormat.book', 'machine', 'press', 'buyer'])
            ->where('status', '!=', ApprovalStatus::Rejected)
            ->where(fn ($q) => $q->where('stage', '!=', WorkOrderStage::Dispatched)->orWhere('updated_at', '>=', now()->subDays(30)))
            ->orderBy('due_on')->orderBy('id')
            ->get();

        return view('work-orders.index', [
            'columns' => collect(WorkOrderStage::cases())->mapWithKeys(fn (WorkOrderStage $s) => [$s->value => $orders->where('stage', $s)->values()]),
            'rejected' => WorkOrder::query()->where('status', ApprovalStatus::Rejected)->latest()->take(5)->get(),
            'inProduction' => $orders->whereIn('stage', [WorkOrderStage::Printing, WorkOrderStage::Folding, WorkOrderStage::Binding]),
            'overdue' => $orders->filter(fn (WorkOrder $o) => $o->due_on && $o->due_on->isPast() && $o->stage !== WorkOrderStage::Dispatched)->count(),
        ]);
    }

    public function create(Request $request, PaperStock $stock): View
    {
        return view('work-orders.create', [
            'number' => WorkOrder::nextNumber(),
            'books' => Book::query()->active()->whereHas('formats')->with('formats')->orderBy('name')->get(),
            'balances' => $stock->balances()->filter(fn (array $row) => $row['balance'] > 0)->values(),
            'machines' => Machine::query()->where('type', 'printing')->orderBy('code')->get(),
            'presses' => Party::query()->active()->where('kind', PartyKind::Press)->orderBy('name')->get(),
            'buyers' => Party::query()->active()->where('kind', PartyKind::Buyer)->orderBy('name')->get(),
            'defaultWastage' => (float) Setting::get(Setting::DefaultWastagePercent, 3),
            'formatId' => $request->integer('format') ?: null,
        ]);
    }

    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $format = BookFormat::query()->findOrFail($data['book_format_id']);
        $isOutsource = $data['mode'] === PrintMode::Outsource->value;

        $order = WorkOrder::create(array_merge($data, [
            'number' => WorkOrder::nextNumber(),
            'forms' => $format->forms,
            'rims_needed' => $request->rimsNeeded(),
            'machine_id' => $isOutsource ? null : $data['machine_id'],
            'press_id' => $isOutsource ? $data['press_id'] : null,
            'stage' => WorkOrderStage::Waiting,
            'created_by' => $request->user()->id,
        ]));

        return redirect()->route('work-orders.show', $order)->with('status', $order->isPending()
            ? __(':number saved and sent for approval. Paper is issued once it is approved.', ['number' => $order->number])
            : __(':number saved; :rims rims issued from stock.', ['number' => $order->number, 'rims' => number_format($order->rims_needed)]));
    }

    /**
     * Everything about one order: paper, outside printing, contractors, plates, waste and dispatch.
     */
    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['bookFormat.book', 'paperType', 'party', 'machine', 'press', 'buyer', 'paperIssue', 'creator', 'reviewer',
            'outsideReceipts', 'contractorJobs.contractor', 'plateJobs.processor', 'dispatches.buyer', 'wasteEntries']);
        $sheets = $workOrder->paperType->sheets_per_unit ?: 500;

        return view('work-orders.show', [
            'order' => $workOrder,
            'rims' => WorkOrder::rims((float) $workOrder->forms, $workOrder->copies, (float) $workOrder->wastage_percent, $sheets),
            'godowns' => Godown::query()->active()->orderBy('name')->get(),
            'dispatched' => $workOrder->dispatches->sum('copies'),
            'printedBack' => $workOrder->outsideReceipts->sum('printed_copies'),
        ]);
    }

    /**
     * Move the order to its next stage.
     */
    public function advance(WorkOrder $workOrder): RedirectResponse
    {
        $next = $workOrder->stage->next();
        abort_unless($next && ! $workOrder->isPending(), 409, __('This order cannot move on yet.'));

        $workOrder->update(['stage' => $next]);

        return back()->with('status', __(':number moved to :stage.', ['number' => $workOrder->number, 'stage' => $next->label()]));
    }

    /**
     * Printed work back from the outside press; unused rims return to the godown as a receipt slip.
     */
    public function receiveOutside(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($workOrder->mode === PrintMode::Outsource && $workOrder->paper_issue_id, 409);

        $data = $request->validate([
            'received_on' => ['required', 'date'],
            'printed_copies' => ['required', 'integer', 'min:0'],
            'leftover_rims' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $workOrder->rimsUsed())],
            'godown_id' => ['nullable', 'required_with:leftover_rims', Rule::exists('godowns', 'id')->where('is_active', true)],
            'bill_amount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $workOrder, $request): void {
            $receipt = null;
            if ((int) ($data['leftover_rims'] ?? 0) > 0) {
                $receipt = PaperReceipt::create([
                    'slip_no' => 'RET-'.$workOrder->number.'-'.(OutsidePrintReceipt::query()->where('work_order_id', $workOrder->id)->count() + 1),
                    'received_at' => $data['received_on'].' '.now()->format('H:i'),
                    'party_id' => $workOrder->party_id, 'paper_type_id' => $workOrder->paper_type_id,
                    'quantity' => $data['leftover_rims'], 'godown_id' => $data['godown_id'],
                    'received_by' => $request->user()->name,
                    'note' => __('Unused paper returned by :press for :number', ['press' => $workOrder->press?->name, 'number' => $workOrder->number]),
                    'created_by' => $request->user()->id,
                ]);
            }

            OutsidePrintReceipt::create([
                'work_order_id' => $workOrder->id, 'received_on' => $data['received_on'],
                'printed_copies' => $data['printed_copies'], 'leftover_rims' => (int) ($data['leftover_rims'] ?? 0),
                'paper_receipt_id' => $receipt?->id, 'bill_amount' => $data['bill_amount'] ?? null,
                'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', __('Printed work received from :press.', ['press' => $workOrder->press?->name]));
    }

    /**
     * Only an order still waiting for approval can be deleted; nothing has left the godown yet.
     */
    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        abort_if($workOrder->paper_issue_id, 409, __('Paper has already been issued for this order.'));

        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('status', __(':number deleted.', ['number' => $workOrder->number]));
    }
}
