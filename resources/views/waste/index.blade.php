<x-layouts.app :title="__('Waste')">
    <x-page-head :title="__('Waste')" :subtitle="__('Waste from each stage of production, who was responsible, and how it was disposed of.')">
        <x-slot:actions>@can('waste.manage')<a class="btn primary" href="{{ route('waste.create') }}"><x-icon name="plus" />{{ __('Record waste') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    <div class="grid g-main">
        <div class="card">
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th>{{ __('Stage') }}</th><th class="r">{{ __('Quantity') }}</th><th>{{ __('Responsible') }}</th><th>{{ __('Disposal') }}</th></tr></thead>
                <tbody>
                @forelse ($entries as $w)
                    <tr>
                        <td>{{ $w->recorded_on->format('j M Y') }}</td>
                        <td>@if ($w->workOrder)<a href="{{ route('work-orders.show', $w->workOrder) }}">{{ $w->workOrder->number }}</a>@else — @endif</td>
                        <td>{{ $w->stage->label() }}</td>
                        <td class="r num"><b>{{ \App\Models\InventoryItem::qty($w->quantity) }}</b> {{ $w->unit }}</td>
                        <td>{{ $w->responsible ?: '—' }}</td>
                        <td><x-badge :tone="$w->disposal->tone()">{{ $w->disposal->label() }}</x-badge>@if ($w->sale_amount)<div class="meta">Rs {{ number_format($w->sale_amount) }}{{ $w->sold_to ? ' · '.$w->sold_to : '' }}</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><b>{{ __('No waste recorded') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $entries->links() }}
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('This month') }}</h3></div></div>
            <div class="card-b">
                @forelse ($byStage as $stage => $total)
                    <div class="stat-line"><span>{{ $stage }}</span><b class="num">{{ \App\Models\InventoryItem::qty($total) }}</b></div>
                @empty
                    <div class="empty">{{ __('Nothing yet') }}</div>
                @endforelse
                <div class="stat-line"><span>{{ __('Waste sold') }}</span><b class="num">Rs {{ number_format($soldThisMonth) }}</b></div>
            </div>
        </div>
    </div>
</x-layouts.app>
