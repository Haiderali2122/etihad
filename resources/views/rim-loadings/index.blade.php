@php $qty = fn ($v) => \App\Models\InventoryItem::qty($v); @endphp
<x-layouts.app :title="__('Younus ka khata')">
    <x-page-head :title="__('Younus ka khata')" :subtitle="__('Every rim Younus moves — godown to our press, our press to folding, godown to the outside press and back — and the outside printing charge he takes to the press. Each line is rims × rate per rim; lines are added from the work orders.')">
        <x-slot:actions>
            @can('orders.manage')
                @if ($unpaidCount)
                    <form method="POST" action="{{ route('rim-loadings.pay-all') }}" onsubmit="return confirm(@js(__('Pay all :n unpaid lines, Rs :amount, today?', ['n' => $unpaidCount, 'amount' => number_format($owed)])))">
                        @csrf<button class="btn primary" type="submit"><x-icon name="wallet" />{{ __('Pay all (Rs :n)', ['n' => number_format($owed)]) }}</button>
                    </form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-head>

    <div class="grid g3">
        <x-kpi icon="wallet" :tone="$owed > 0 ? 'bad' : 'good'" :label="__('Owed to Younus')" :foot="trans_choice(':count unpaid line|:count unpaid lines', $unpaidCount)">Rs {{ number_format($owed) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Paid this month')">Rs {{ number_format($paidThisMonth) }}</x-kpi>
        <x-kpi icon="paper" :label="__('Rims moved this month')">{{ $qty($rims) }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs">
                @foreach (['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'all' => __('All')] as $key => $label)
                    <a href="{{ route('rim-loadings.index', array_filter(['show' => $key, 'kind' => $kind?->value])) }}" @class(['tab', 'on' => $show === $key]) style="text-decoration:none">{{ $label }}</a>
                @endforeach
            </div>
            <form class="filters" method="GET">
                <input type="hidden" name="show" value="{{ $show }}">
                <select class="select" name="kind" aria-label="{{ __('Type') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All types') }}</option>
                    @foreach (\App\Enums\RimMove::cases() as $k)<option value="{{ $k->value }}" @selected($kind === $k)>{{ $k->label() }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Work order') }}</th><th class="r">{{ __('Rims') }}</th><th class="r">{{ __('Rate per rim') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Paid') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($loadings as $l)
                <tr>
                    <td>{{ $l->loaded_on->format('j M Y') }}</td>
                    <td><x-badge :tone="$l->kind->tone()">{{ $l->kind->label() }}</x-badge>@if ($l->note)<div class="meta">{{ $l->note }}</div>@endif</td>
                    <td><a class="main" href="{{ route('work-orders.show', $l->work_order_id) }}" style="color:inherit">{{ $l->workOrder->number }}</a><div class="meta">{{ $l->workOrder->bookName() }}</div></td>
                    <td class="r num">{{ $qty($l->rims) }}</td>
                    <td class="r num">@if ((float) $l->rate_per_rim > 0){{ $qty($l->rate_per_rim) }}@else<span style="color:var(--warn)">{{ __('Set rate') }}</span>@endif</td>
                    <td class="r num"><b>Rs {{ number_format($l->amount) }}</b></td>
                    <td>
                        @if ($l->isPaid())
                            <x-badge tone="good">{{ $l->paid_on->format('j M Y') }}</x-badge>
                        @else
                            @can('orders.manage')
                                @if ($l->amount > 0)
                                    <form method="POST" action="{{ route('rim-loadings.pay', $l) }}">@csrf @method('PATCH')<button class="btn sm good" type="submit"><x-icon name="check" />{{ __('Pay') }}</button></form>
                                @endif
                            @else
                                <x-badge tone="warn">{{ __('Unpaid') }}</x-badge>
                            @endcan
                        @endif
                    </td>
                    <td class="r" style="white-space:nowrap">@unless ($l->isPaid())@can('orders.manage')<a class="btn sm" href="{{ route('rim-loadings.edit', $l) }}"><x-icon name="edit" />{{ __('Edit') }}</a> <x-delete-button :action="route('rim-loadings.destroy', $l)" :label="''" small aria-label="{{ __('Delete') }}" />@endcan @endunless</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('Nothing here') }}</b>{{ __('Lines are added when paper is issued for a work order, when printing is done, and when printed work comes back from an outside press.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $loadings->links() }}
    </div>
</x-layouts.app>
