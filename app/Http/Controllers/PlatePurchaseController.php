<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\PlateJob;
use App\Models\PlatePurchase;
use App\Support\OtherOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Plate stock: blank plates bought by size (each purchase a bill paid in parts) and what is left after the
 * plate jobs given to processors.
 */
class PlatePurchaseController extends Controller
{
    public function index(): View
    {
        return view('plate-stock.index', [
            'stock' => PlatePurchase::stockBySize(),
            'purchases' => PlatePurchase::query()->with('payments')->latest('purchased_on')->latest('id')->paginate(30),
            'unpaid' => (int) PlatePurchase::query()->sum(DB::raw('amount - paid_amount')),
        ]);
    }

    public function create(): View
    {
        return $this->form(new PlatePurchase(['purchased_on' => today()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $purchase = DB::transaction(function () use ($data, $request): PlatePurchase {
            $purchase = PlatePurchase::create($data + ['attachment' => $this->storeAttachment($request, 'plates'), 'created_by' => $request->user()->id]);
            if ((int) $request->input('paid_now') > 0) {
                $purchase->pay((int) $request->input('paid_now'), $purchase->purchased_on, $request->user()->id);
            }

            return $purchase;
        });

        return redirect()->route('plate-stock.index')->with('status', __(':n plates of :size added to stock; Rs :unpaid unpaid.', ['n' => $purchase->plates, 'size' => $purchase->plate_size, 'unpaid' => number_format($purchase->refresh()->unpaid())]));
    }

    public function edit(PlatePurchase $platePurchase): View
    {
        return $this->form($platePurchase);
    }

    public function update(Request $request, PlatePurchase $platePurchase): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data['amount'] < $platePurchase->paid_amount) {
            return back()->withInput()->withErrors(['rate' => __('Rs :paid has already been paid on this bill, so the total cannot be less.', ['paid' => number_format($platePurchase->paid_amount)])]);
        }
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->storeAttachment($request, 'plates');
        }
        $platePurchase->update($data);

        return redirect()->route('plate-stock.index')->with('status', __('Plate purchase saved.'));
    }

    private function form(PlatePurchase $purchase): View
    {
        // The last price per plate for each size (and for each supplier and size), filled in when picked.
        $rates = [];
        PlatePurchase::query()->orderBy('purchased_on')->orderBy('id')->get(['supplier', 'plate_size', 'rate'])
            ->each(function (PlatePurchase $p) use (&$rates): void {
                $size = mb_strtolower(trim($p->plate_size));
                $rates[$size][''] = (float) $p->rate;
                if ($p->supplier) {
                    $rates[$size][mb_strtolower(trim($p->supplier))] = (float) $p->rate;
                }
            });

        return view('plate-stock.form', [
            'purchase' => $purchase,
            'sizes' => PlatePurchase::query()->distinct()->pluck('plate_size')
                ->merge(PlateJob::query()->whereNotNull('plate_size')->distinct()->pluck('plate_size'))
                ->map(fn ($s) => trim((string) $s))->filter()->unique(fn ($s) => mb_strtolower($s))->sort()->values()->all(),
            'suppliers' => Party::query()->active()->where('kind', PartyKind::Supplier)->orderBy('name')->pluck('name')->all(),
            'lastRates' => $rates,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        // A supplier is typed or picked; a new name joins Parties as a supplier.
        $supplier = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('supplier')));
        if ($supplier !== '') {
            $supplier = OtherOption::findOrCreate(Party::class, $supplier, ['kind' => PartyKind::Supplier, 'is_active' => true])->name;
        }
        $request->merge(['supplier' => $supplier ?: null, 'plate_size' => trim((string) $request->input('plate_size'))]);

        $data = $request->validate([
            'purchased_on' => ['required', 'date', 'before_or_equal:today'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'plate_size' => ['required', 'string', 'max:40'],
            'plates' => ['required', 'integer', 'min:1'],
            'rate' => ['required', 'numeric', 'min:0'],
            'bill_no' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'paid_now' => ['nullable', 'integer', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);
        // The same size typed differently is kept as one size.
        $data['plate_size'] = PlatePurchase::query()->whereRaw('LOWER(TRIM(plate_size)) = ?', [mb_strtolower($data['plate_size'])])->value('plate_size')
            ?? PlateJob::query()->whereRaw('LOWER(TRIM(plate_size)) = ?', [mb_strtolower($data['plate_size'])])->value('plate_size')
            ?? $data['plate_size'];
        unset($data['paid_now'], $data['attachment']);

        return $data + ['amount' => (int) round($data['plates'] * (float) $data['rate'])];
    }
}
