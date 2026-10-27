<x-layouts.app :title="__('Loading & dispatch')">
    <x-page-head :title="__('Loading & dispatch')" :subtitle="__('Books loaded out to buyers, and the loading labour paid for each load.')">
        <x-slot:actions>@can('dispatch.manage')<a class="btn primary" href="{{ route('dispatches.create') }}"><x-icon name="truck" />{{ __('New loading') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    <div class="grid g3">
        <x-kpi icon="truck" :label="__('Copies sent this month')">{{ number_format($monthCopies) }}</x-kpi>
        <x-kpi icon="box" :label="__('Bundles this month')">{{ number_format($monthBundles) }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Loading labour this month')">Rs {{ number_format($monthLabour) }}</x-kpi>
    </div>
    <div class="card">
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Load') }}</th><th>{{ __('Date') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Book') }}</th><th class="r">{{ __('Bundles') }}</th><th class="r">{{ __('Copies') }}</th><th class="r">{{ __('Labour') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($dispatches as $d)
                <tr>
                    <td><a class="main" href="{{ route('dispatches.show', $d) }}" style="color:inherit;text-decoration:none">{{ $d->number }}</a>@if ($d->workOrder)<div class="meta">{{ $d->workOrder->number }}</div>@endif</td>
                    <td>{{ $d->dispatched_on->format('j M Y') }}@if ($d->truck_no)<div class="meta">{{ $d->truck_no }}</div>@endif</td>
                    <td>{{ $d->buyer->name }}<div class="meta">{{ $d->destination }}</div></td>
                    <td>{{ $d->bookName() }}</td>
                    <td class="r num">{{ number_format($d->bundles) }}<div class="meta">× {{ $d->copies_per_bundle }}</div></td>
                    <td class="r num"><b>{{ number_format($d->copies) }}</b></td>
                    <td class="r num">Rs {{ number_format($d->labour_amount) }}</td>
                    <td class="r"><a class="btn sm" href="{{ route('dispatches.show', $d) }}"><x-icon name="printer" />{{ __('Slip') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><b>{{ __('Nothing loaded yet') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $dispatches->links() }}
    </div>
</x-layouts.app>
