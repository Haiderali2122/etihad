<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Models\BillPayment;
use App\Models\InventoryCategory;
use App\Models\MaterialBill;
use App\Models\Party;
use App\Models\PlateJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Paying Diesel, Glue and Ink bills and plate bills: any amount on one bill, an amount to a processor that clears
 * their oldest bills first, or everything unpaid at once. Every payment is dated and comes off the manager's cash.
 */
class BillPaymentController extends Controller
{
    public function payBill(Request $request, MaterialBill $materialBill): RedirectResponse
    {
        return $this->payOne($request, $materialBill, $materialBill->bill_on);
    }

    public function payPlate(Request $request, PlateJob $plateJob): RedirectResponse
    {
        return $this->payOne($request, $plateJob, $plateJob->job_on);
    }

    /**
     * Everything still unpaid on the tab, paid on one date.
     */
    public function payAllBills(Request $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        $bills = MaterialBill::query()->where('inventory_category_id', $inventoryCategory->id)->whereColumn('paid_amount', '<', 'total')->orderBy('bill_on')->orderBy('id')->get();

        return $this->payAll($request, $bills);
    }

    /**
     * Every unpaid plate bill, or only one processor's.
     */
    public function payAllPlates(Request $request): RedirectResponse
    {
        $jobs = PlateJob::query()->with('processor')->whereColumn('paid_amount', '<', 'amount')
            ->when($request->integer('processor'), fn ($q, $id) => $q->where('party_id', $id))
            ->orderBy('job_on')->orderBy('id')->get();

        return $this->payAll($request, $jobs);
    }

    /**
     * Any amount given to a processor: it clears their oldest unpaid plate bills first; what is left stays unpaid.
     */
    public function payProcessor(Request $request, Party $processor): RedirectResponse
    {
        abort_unless($processor->kind === PartyKind::PlateProcessor, 404);
        $jobs = PlateJob::query()->where('party_id', $processor->id)->whereColumn('paid_amount', '<', 'amount')->orderBy('job_on')->orderBy('id')->get();
        $owed = (int) $jobs->sum(fn (PlateJob $j) => $j->unpaid());

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $owed)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => __('Only Rs :n is unpaid.', ['n' => number_format($owed)])]);

        $left = (int) $data['amount'];
        DB::transaction(function () use ($jobs, &$left, $data, $request): void {
            foreach ($jobs as $job) {
                if ($left <= 0) {
                    break;
                }
                $left -= (int) $job->pay($left, $data['paid_on'], $request->user()->id, $data['note'] ?? null)?->amount;
            }
        });
        $remaining = $owed - ((int) $data['amount'] - $left);

        return back()->with('status', __('Rs :paid paid to :name; Rs :left still unpaid.', ['paid' => number_format((int) $data['amount'] - $left), 'name' => $processor->name, 'left' => number_format($remaining)]));
    }

    /**
     * A payment entered by mistake; it leaves Cash-out and the bill shows it unpaid again.
     */
    public function destroy(BillPayment $billPayment): RedirectResponse
    {
        abort_unless(Gate::allows($billPayment->payable instanceof PlateJob ? 'plates.manage' : 'expenses.create'), 403);
        $billPayment->delete();

        return back()->with('status', __('Payment of Rs :n deleted.', ['n' => number_format($billPayment->amount)]));
    }

    /**
     * @param  MaterialBill|PlateJob  $payable
     */
    private function payOne(Request $request, Model $payable, Carbon $from): RedirectResponse
    {
        $unpaid = $payable->unpaid();
        // Without an amount the whole unpaid balance is paid ("Mark paid").
        $request->mergeIfMissing(['amount' => $unpaid]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $unpaid)],
            // Any date up to today: payments are often entered later, or made before the bill is written up.
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => __('Only Rs :n is unpaid.', ['n' => number_format($unpaid)])]);

        $payable->pay((int) $data['amount'], $data['paid_on'], $request->user()->id, ($data['note'] ?? null) ?: ($data['payment_note'] ?? null));
        $payable->refresh();

        return back()->with('status', $payable->unpaid() > 0
            ? __('Rs :paid paid; Rs :left still unpaid.', ['paid' => number_format($data['amount']), 'left' => number_format($payable->unpaid())])
            : __('Rs :paid paid; the bill is cleared.', ['paid' => number_format($data['amount'])]));
    }

    /**
     * @param  Collection<int, MaterialBill|PlateJob>  $payables
     */
    private function payAll(Request $request, Collection $payables): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $on = $data['paid_on'] ?? today()->toDateString();

        $total = 0;
        DB::transaction(function () use ($payables, $on, $request, &$total): void {
            foreach ($payables as $payable) {
                $total += (int) $payable->pay($payable->unpaid(), $on, $request->user()->id)?->amount;
            }
        });

        return back()->with('status', $total > 0 ? __('Rs :n paid; nothing is unpaid now.', ['n' => number_format($total)]) : __('Nothing is unpaid.'));
    }
}
