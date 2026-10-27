<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\StorePaperReceiptRequest;
use App\Models\Godown;
use App\Models\PaperReceipt;
use App\Models\PaperType;
use App\Models\Party;
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
