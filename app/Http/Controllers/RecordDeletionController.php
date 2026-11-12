<?php

namespace App\Http\Controllers;

use App\Models\BuyerEntry;
use App\Models\CashFloat;
use App\Models\Dispatch;
use App\Models\ForemanSheet;
use App\Models\InventoryIssue;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Machine;
use App\Models\MachineRepair;
use App\Models\OutsidePrintReceipt;
use App\Models\PaperIssue;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\Purchase;
use App\Models\SalaryAdvance;
use App\Models\Sale;
use App\Models\WasteEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes the records that have no delete of their own. Each type keeps the permission that manages it; the
 * GuardDeletions middleware has already checked "Delete records" and the reason, and the record logs itself.
 */
class RecordDeletionController extends Controller
{
    /**
     * @var array<string, array{0: class-string<Model>, 1: string}> type in the URL => [model, permission]
     */
    public const Types = [
        'paper-receipt' => [PaperReceipt::class, 'paper.receive'],
        'paper-issue' => [PaperIssue::class, 'paper.issue'],
        'paper-type' => [PaperType::class, 'paper.types'],
        'purchase' => [Purchase::class, 'purchases.create'],
        'inventory-issue' => [InventoryIssue::class, 'inventory.issue'],
        'machine' => [Machine::class, 'machines.manage'],
        'machine-repair' => [MachineRepair::class, 'machines.repair'],
        'party' => [Party::class, 'parties.manage'],
        'dispatch' => [Dispatch::class, 'dispatch.manage'],
        'buyer-entry' => [BuyerEntry::class, 'buyers.manage'],
        'waste-entry' => [WasteEntry::class, 'waste.manage'],
        'outside-print-receipt' => [OutsidePrintReceipt::class, 'orders.manage'],
        'foreman-sheet' => [ForemanSheet::class, 'advances.create'],
        'salary-advance' => [SalaryAdvance::class, 'advances.create'],
        'loan' => [Loan::class, 'advances.create'],
        'loan-repayment' => [LoanRepayment::class, 'advances.create'],
        'payroll-run' => [PayrollRun::class, 'payroll.prepare'],
        'cash-float' => [CashFloat::class, 'give-cash'],
        'sale' => [Sale::class, 'expenses.create'],
    ];

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::Types[$type]), 404);
        [$class, $permission] = self::Types[$type];
        abort_unless(Gate::allows($permission), 403);

        /** @var Model $record */
        $record = $class::query()->findOrFail($id);

        if ($blocker = $record->deletionBlocker()) {
            return back()->withErrors(['delete' => $blocker]);
        }

        DB::transaction(fn () => $record->delete());

        return redirect($this->safeReturnUrl($request->input('return_to')) ?? url()->previous())
            ->with('status', __(':kind deleted.', ['kind' => $record->deletionKind()]));
    }
}
