<x-layouts.app :title="__('Plate stock')">
    <x-page-head :title="__('Plate stock')" :subtitle="__('Blank plates bought by size, what the plate jobs have used, and what is left. Each purchase is a bill paid later; payments come off the manager\'s cash.')">
        <x-slot:actions>@can('purchases.create')<a class="btn primary" href="{{ route('plate-stock.create') }}"><x-icon name="plus" />{{ __('Add plate purchase') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="card-h"><div><h3>{{ __('In stock') }}</h3><div class="sub">{{ __('Bought less given out on plate jobs, by size.') }}</div></div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Plate size') }}</th><th class="r">{{ __('Bought') }}</th><th class="r">{{ __('Used on plate jobs') }}</th><th class="r">{{ __('In stock') }}</th></tr></thead>
            <tbody>
            @forelse ($stock as $row)
                <tr>
                    <td><b>{{ $row['size'] }}</b></td>
                    <td class="r num">{{ number_format($row['bought']) }}</td>
                    <td class="r num">{{ number_format($row['used']) }}</td>
                    <td class="r num"><b @style(['color:var(--bad)' => $row['stock'] <= 0])>{{ number_format($row['stock']) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><b>{{ __('No plates bought yet') }}</b>{{ __('Add them with Add plate purchase.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:8px">
            <div><h3 style="margin:0">{{ __('Purchases') }}</h3>@if ($unpaid > 0)<b style="color:var(--bad)">{{ __('Unpaid: Rs :n', ['n' => number_format($unpaid)]) }}</b>@endif</div>
            @can('purchases.create')@if ($unpaid > 0)
                <form method="POST" action="{{ route('plate-stock.pay-all') }}" class="inline-form" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm(@js(__('Pay all unpaid plate bills (Rs :n)?', ['n' => number_format($unpaid)])))">@csrf
                    <input class="select" style="width:140px" type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Paid on') }}">
                    <button class="btn good" type="submit"><x-icon name="check" />{{ __('Pay all (Rs :n)', ['n' => number_format($unpaid)]) }}</button>
                </form>
            @endif @endcan
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Plate size') }}</th><th class="r">{{ __('Plates') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Total') }}</th><th>{{ __('Payments') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($purchases as $p)
                <tr data-href="{{ route('records.show', ['plate-purchase', $p->id]) }}">
                    <td>{{ $p->purchased_on->format('j M Y') }}@if ($p->bill_no)<div class="meta">{{ __('Bill :no', ['no' => $p->bill_no]) }}</div>@endif</td>
                    <td>{{ $p->supplier ?: '—' }}</td>
                    <td><b>{{ $p->plate_size }}</b></td>
                    <td class="r num">{{ number_format($p->plates) }}</td>
                    <td class="r num">{{ number_format((float) $p->rate, 2) }}</td>
                    <td class="r num"><b>Rs {{ number_format($p->amount) }}</b></td>
                    <td>@include('partials.bill-payments', ['payable' => $p, 'payUrl' => route('plate-stock.pay', $p), 'canPay' => auth()->user()->can('purchases.create')])</td>
                    <td class="r" style="white-space:nowrap">@include('partials.attachment', ['path' => $p->attachment])@can('purchases.create')<a class="btn sm" href="{{ route('plate-stock.edit', $p) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                        <x-delete-button :action="route('records.destroy', ['plate-purchase', $p->id])" :confirm="$p->paid_amount > 0 ? __('Delete this plate purchase? Its payments are taken out of Cash-out too.') : __('Delete this plate purchase?')" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('No plate purchases yet') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $purchases->links() }}
    </div>
</x-layouts.app>
