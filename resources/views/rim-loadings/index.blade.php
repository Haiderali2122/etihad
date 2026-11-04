<x-layouts.app :title="__('Younus ka khata')">
    <x-page-head :title="__('Younus ka khata')" :subtitle="__('Printed rims loaded from the press to folding, paid per rim.')" />

    <div class="grid g4">
        <x-kpi icon="truck" :label="__('Earned')" :foot="__('All loadings')">Rs {{ number_format($earned) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Paid')">Rs {{ number_format($paid) }}</x-kpi>
        <x-kpi icon="wallet" :tone="$earned - $paid > 0 ? 'bad' : 'good'" :label="__('Still to pay')">Rs {{ number_format($earned - $paid) }}</x-kpi>
        <x-kpi icon="paper" :label="__('Rims this month')">{{ \App\Models\InventoryItem::qty($rims) }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar"><div class="tabs">
            @foreach (['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'all' => __('All')] as $key => $label)
                <a href="{{ route('rim-loadings.index', ['show' => $key]) }}" @class(['tab', 'on' => $show === $key]) style="text-decoration:none">{{ $label }}</a>
            @endforeach
        </div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Work order') }}</th><th>{{ __('Loaded by') }}</th><th class="r">{{ __('Rims') }}</th><th class="r">{{ __('Rate per rim') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Paid') }}</th></tr></thead>
            <tbody>
            @forelse ($loadings as $l)
                <tr>
                    <td>{{ $l->loaded_on->format('j M Y') }}</td>
                    <td><a class="main" href="{{ route('work-orders.show', $l->work_order_id) }}" style="color:inherit">{{ $l->workOrder->number }}</a><div class="meta">{{ $l->workOrder->bookName() }}</div></td>
                    <td>{{ $l->loaded_by }}@if ($l->note)<div class="meta">{{ $l->note }}</div>@endif</td>
                    <td class="r num">{{ \App\Models\InventoryItem::qty($l->rims) }}</td>
                    <td class="r num">{{ \App\Models\InventoryItem::qty($l->rate_per_rim) }}</td>
                    <td class="r num"><b>Rs {{ number_format($l->amount) }}</b></td>
                    <td>
                        @if ($l->isPaid())
                            <x-badge tone="good">{{ $l->paid_on->format('j M Y') }}</x-badge>
                        @else
                            @can('orders.manage')
                                <form method="POST" action="{{ route('rim-loadings.pay', $l) }}" class="filters" style="flex-wrap:nowrap">@csrf @method('PATCH')
                                    <input class="select" type="date" name="paid_on" value="{{ today()->toDateString() }}" min="{{ $l->loaded_on->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Paid on') }}">
                                    <button class="btn sm" type="submit"><x-icon name="check" />{{ __('Pay') }}</button>
                                </form>
                            @else
                                <x-badge tone="warn">{{ __('Unpaid') }}</x-badge>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><b>{{ __('No loadings yet') }}</b>{{ __('Record them on the work order once printing is done.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $loadings->links() }}
    </div>
</x-layouts.app>
