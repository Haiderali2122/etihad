@php use App\Models\InventoryItem; $level = $item->level(); @endphp
<x-layouts.app :title="$item->name">
    <x-page-head :title="$item->localName()" :subtitle="$item->category->localName().' · '.__('counted in :unit', ['unit' => $item->unit])">
        <x-slot:actions>
            <a class="btn" href="{{ route('inventory.index') }}">{{ __('Back') }}</a>
            @can('inventory.manage')
                <a class="btn" href="{{ route('inventory-items.edit', $item) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                @if ($ledger->isEmpty())
                    <form method="POST" action="{{ route('inventory-items.destroy', $item) }}" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $item->name])))">
                        @csrf @method('DELETE')
                        <button class="btn" type="submit" style="color:var(--bad)"><x-icon name="x" />{{ __('Delete') }}</button>
                    </form>
                @endif
            @endcan
            @can('inventory.issue')
                @if ($item->is_active && $item->category->tracks_stock && $item->balance() > 0)<a class="btn" href="{{ route('inventory-issues.create', ['item' => $item->id]) }}"><x-icon name="send" />{{ __('Move out') }}</a>@endif
            @endcan
            @can('purchases.create')
                @if ($item->is_active)<a class="btn primary" href="{{ route('purchases.create', ['item' => $item->id]) }}"><x-icon name="download" />{{ __('Add purchase') }}</a>@endif
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g4">
        <x-kpi icon="box" :tone="['ok' => 'good', 'low' => 'warn', 'out' => 'bad', 'none' => ''][$level]" :label="__('Balance')" :foot="(float) $item->reorder_level ? __('Reorder at :n', ['n' => InventoryItem::qty($item->reorder_level)]) : __('No reorder level set')">{{ InventoryItem::qty($item->balance()) }}<small>{{ $item->unit }}</small></x-kpi>
        <x-kpi icon="download" :label="__('Bought')">{{ InventoryItem::qty($item->stock_in) }}<small>{{ $item->unit }}</small></x-kpi>
        <x-kpi icon="send" :label="__('Moved out')">{{ InventoryItem::qty($item->stock_out) }}<small>{{ $item->unit }}</small></x-kpi>
        <x-kpi icon="clock" :label="__('Entries')">{{ $ledger->count() }}</x-kpi>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Stock ledger') }}</h3><div class="sub">{{ __('Every purchase in and every move out, newest first') }}</div></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From / to') }}</th><th>{{ __('Reference') }}</th><th class="r">{{ __('In') }}</th><th class="r">{{ __('Out') }}</th><th class="r">{{ __('Balance') }}</th></tr></thead>
            <tbody>
            @forelse ($ledger as $row)
                <tr @unless ($row['counts']) style="opacity:.55" @endunless>
                    <td>{{ $row['date']->format('j M Y') }}</td>
                    <td>
                        @if ($row['kind'] === 'in')
                            <x-badge :tone="$row['status']->tone()">{{ __('Purchase') }} · {{ $row['status']->label() }}</x-badge>
                        @else
                            <x-badge tone="info">{{ __('Moved out') }}</x-badge>
                        @endif
                    </td>
                    <td>{{ $row['who'] }}</td>
                    <td>{{ $row['ref'] ?: '—' }}</td>
                    <td class="r num">{{ $row['kind'] === 'in' ? InventoryItem::qty($row['qty']) : '' }}</td>
                    <td class="r num">{{ $row['kind'] === 'out' ? InventoryItem::qty($row['qty']) : '' }}</td>
                    <td class="r num"><b>{{ InventoryItem::qty($row['balance']) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><b>{{ __('No stock movements yet') }}</b>{{ __('Record a purchase to bring this item into stock.') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
</x-layouts.app>
