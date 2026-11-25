<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\ProcessorPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Money given to a plate processor, from the Plates tab or the processor's page: it clears the oldest unpaid plate
 * bills, and anything more is kept as an advance for the next bills.
 */
class ProcessorPaymentController extends Controller
{
    public function store(Request $request, ?Party $processor = null): RedirectResponse
    {
        if ($processor) {
            abort_unless($processor->kind === PartyKind::PlateProcessor, 404);
            $request->merge(['party_id' => $processor->id]);
        } else {
            $this->resolveProcessor($request);
        }
        $data = $request->validate([
            'party_id' => ['required', Rule::exists('parties', 'id')->where('kind', PartyKind::PlateProcessor->value)],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['party_id.required' => __('Type or pick the processor.')]);

        $payment = DB::transaction(function () use ($data, $request): ProcessorPayment {
            $payment = ProcessorPayment::create($data + ['created_by' => $request->user()->id]);
            ProcessorPayment::applyFor($payment->party_id);

            return $payment;
        });
        $used = $payment->used();
        $left = (int) $payment->amount - $used;
        $owed = (int) $payment->processor->plateJobs()->whereColumn('paid_amount', '<', 'amount')->sum(DB::raw('amount - paid_amount'));

        $message = __('Rs :n given to :name.', ['n' => number_format($payment->amount), 'name' => $payment->processor->name]);
        if ($used > 0) {
            $message .= ' '.__('Rs :n paid on plate bills.', ['n' => number_format($used)]);
        }
        $message .= ' '.($left > 0
            ? __('Rs :n kept as advance for the next plate bills.', ['n' => number_format($left)])
            : __('Rs :n still to pay.', ['n' => number_format($owed)]));

        return back()->with('status', $message);
    }

    /**
     * The processor is typed or picked; a new name is added to Processors.
     */
    private function resolveProcessor(Request $request): void
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('processor_name')));
        if ($name === '') {
            return;
        }
        $processor = Party::query()->where('kind', PartyKind::PlateProcessor)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->first()
            ?? Party::query()->create(['name' => $name, 'kind' => PartyKind::PlateProcessor, 'is_active' => true]);
        $request->merge(['party_id' => $processor->id]);
    }
}
