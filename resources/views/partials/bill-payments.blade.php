{{-- A bill's payments: each with its date and amount, what is still unpaid, and a Pay box (any amount, with its date).
     Expects $payable (MaterialBill or PlateJob), $payUrl and $canPay. --}}
@php $unpaid = $payable->unpaid(); @endphp
@if ($unpaid <= 0)
    <x-badge tone="good">{{ __('Paid') }}</x-badge>
@elseif ($payable->paid_amount > 0)
    <x-badge tone="warn">{{ __('Rs :n unpaid', ['n' => number_format($unpaid)]) }}</x-badge>
@else
    <x-badge tone="bad">{{ __('Unpaid') }}</x-badge>
@endif
@foreach ($payable->payments as $pay)
    <div class="meta" style="white-space:nowrap">{{ $pay->paid_on->format('j M') }}: Rs {{ number_format($pay->amount) }}@if ($pay->note) · {{ $pay->note }}@endif
        @if ($canPay)<x-delete-button :action="route('bill-payments.destroy', $pay)" :confirm="__('Delete this payment of Rs :n? It is taken out of Cash-out too.', ['n' => number_format($pay->amount)])" :label="''" small :danger="false" aria-label="{{ __('Delete payment') }}" />@endif
    </div>
@endforeach
@if ($canPay && $unpaid > 0)
    {{-- Pay any part of what is unpaid, on any date up to today. --}}
    <form method="POST" action="{{ $payUrl }}" style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end;margin-top:6px">@csrf @method('PATCH')
        <label style="display:flex;flex-direction:column;font-size:12px">{{ __('Amount') }}
            <input class="select" style="width:100px" type="number" name="amount" min="1" max="{{ $unpaid }}" step="1" value="{{ $unpaid }}" required>
        </label>
        <label style="display:flex;flex-direction:column;font-size:12px">{{ __('Paid on') }}
            <input class="select" style="width:140px" type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
        </label>
        <button class="btn sm good" type="submit"><x-icon name="check" />{{ __('Pay') }}</button>
    </form>
@endif
