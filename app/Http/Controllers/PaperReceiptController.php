<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PartyKind;
use App\Http\Requests\StorePaperReceiptRequest;
use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
use App\Support\PaperStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaperReceiptController extends Controller
{
    public function create(Request $request): View
    {
        $lastSlip = PaperReceipt::query()->pluck('slip_no')
            ->filter(fn (string $slip): bool => ctype_digit($slip))
            ->map(fn (string $slip): int => (int) $slip)
            ->max();

        return view('paper.receipts.create', [
            'parties' => Party::query()->active()->whereIn('kind', [PartyKind::PaperParty, PartyKind::Supplier])->orderBy('name')->get(),
            'paperTypes' => PaperType::query()->active()->orderBy('code')->get(),
            'nextSlip' => $lastSlip === null ? '' : (string) ($lastSlip + 1),
            'godowns' => Godown::query()->active()->orderBy('name')->get(),
            'receivedBy' => $request->user()->name,
        ]);
    }

    public function store(StorePaperReceiptRequest $request): RedirectResponse
    {
        $receipt = PaperReceipt::create($request->safe()->except('attachment') + [
            'attachment' => $this->storeAttachment($request, 'paper'),
            'created_by' => $request->user()->id,
        ]);

        $message = $receipt->isPending()
            ? __('Slip :no saved and sent for approval.', ['no' => $receipt->slip_no])
            : __('Slip :no saved.', ['no' => $receipt->slip_no]);

        return redirect()->route('paper-receipts.show', $receipt)->with('status', $message);
    }

    public function edit(Request $request, PaperReceipt $paperReceipt): View
    {
        return view('paper.receipts.create', [
            'receipt' => $paperReceipt,
            'parties' => Party::query()->whereIn('kind', [PartyKind::PaperParty, PartyKind::Supplier])
                ->where(fn ($q) => $q->where('is_active', true)->orWhereKey($paperReceipt->party_id))->orderBy('name')->get(),
            'paperTypes' => PaperType::query()->where(fn ($q) => $q->where('is_active', true)->orWhereKey($paperReceipt->paper_type_id))->orderBy('code')->get(),
            'godowns' => Godown::query()->where(fn ($q) => $q->where('is_active', true)->orWhereKey($paperReceipt->godown_id))->orderBy('name')->get(),
            'nextSlip' => $paperReceipt->slip_no,
            'receivedBy' => $paperReceipt->received_by,
        ]);
    }

    /**
     * Correct a slip. Paper already issued from it must still be covered, so the rims cannot drop below what
     * has gone out. The owner's changes stand; anyone else's go back to the owner for approval.
     */
    public function update(StorePaperReceiptRequest $request, PaperReceipt $paperReceipt, PaperStock $stock): RedirectResponse
    {
        $data = $request->safe()->except('attachment');
        $samePile = (int) $data['paper_type_id'] === $paperReceipt->paper_type_id && (int) $data['party_id'] === $paperReceipt->party_id;
        $leftInOldPile = $stock->available($paperReceipt->paper_type_id, $paperReceipt->party_id)
            - ($paperReceipt->status === ApprovalStatus::Rejected ? 0 : $paperReceipt->quantity)
            + ($samePile ? (int) $data['quantity'] : 0);

        if ($leftInOldPile < 0) {
            return back()->withInput()->withErrors(['quantity' => __(':n more rims of this paper have already been issued than this slip would leave in stock.', ['n' => number_format(-$leftInOldPile)])]);
        }

        $paperReceipt->fill($data);
        if ($request->hasFile('attachment')) {
            $paperReceipt->attachment = $this->storeAttachment($request, 'paper');
        }
        $user = $request->user();
        $paperReceipt->forceFill($user->can('approve')
            ? ['status' => ApprovalStatus::Approved, 'reviewed_by' => $user->id, 'reviewed_at' => now()]
            : ['status' => ApprovalStatus::Pending, 'reviewed_by' => null, 'reviewed_at' => null])->save();

        return redirect()->route('paper-receipts.show', $paperReceipt)->with('status', $paperReceipt->isPending()
            ? __('Slip :no changed and sent to the owner for approval.', ['no' => $paperReceipt->slip_no])
            : __('Slip :no saved.', ['no' => $paperReceipt->slip_no]));
    }

    /**
     * The digital receipt slip, drawn on the Etihad letterhead and ready to print.
     */
    public function show(PaperReceipt $paperReceipt): View
    {
        return view('paper.receipts.show', [
            'receipt' => $paperReceipt->load(['party', 'paperType', 'godown', 'creator', 'reviewer']),
        ]);
    }
}
