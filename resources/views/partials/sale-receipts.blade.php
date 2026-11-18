{{-- Money received for a raddi sale: each amount with its date, what is still to come, and a Receive box. Expects $sale. --}}
@php $left = $sale->toReceive(); $canReceive = auth()->user()->can('expenses.create'); @endphp
@if ($left <= 0)
    <x-badge tone="good">{{ __('Received') }}</x-badge>
@elseif ($sale->received_amount > 0)
    <x-badge tone="warn">{{ __('Rs :n to receive', ['n' => number_format($left)]) }}</x-badge>
@else
    <x-badge tone="bad">{{ __('Not received') }}</x-badge>
@endif
@foreach ($sale->receipts as $r)
    <div class="meta" style="white-space:nowrap">{{ $r->received_on->format('j M') }}: Rs {{ number_format($r->amount) }}@if ($r->note) · {{ $r->note }}@endif
        @if ($canReceive)<x-delete-button :action="route('records.destroy', ['sale-receipt', $r->id])" :confirm="__('Delete this receipt of Rs :n?', ['n' => number_format($r->amount)])" :label="''" small :danger="false" aria-label="{{ __('Delete') }}" />@endif
    </div>
@endforeach
@if ($canReceive && $left > 0)
    <form method="POST" action="{{ route('sales.receive', $sale) }}" style="display:flex;flex-wrap:wrap;gap:6px;align-items:flex-end;margin-top:6px">@csrf @method('PATCH')
        <label style="display:flex;flex-direction:column;font-size:12px">{{ __('Amount') }}
            <input class="select" style="width:100px" type="number" name="amount" min="1" max="{{ $left }}" step="1" value="{{ $left }}" required>
        </label>
        <label style="display:flex;flex-direction:column;font-size:12px">{{ __('Received on') }}
            <input class="select" style="width:140px" type="date" name="received_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
        </label>
        <button class="btn sm good" type="submit"><x-icon name="check" />{{ __('Receive') }}</button>
    </form>
@endif
