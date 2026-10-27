@php
    use App\Models\InventoryItem;
    $levelTone = ['ok' => 'good', 'low' => 'warn', 'out' => 'bad', 'none' => 'mute'];
    $levelLabel = ['ok' => __('OK'), 'low' => __('Low'), 'out' => __('Out of stock'), 'none' => __('No stock yet')];
    $tabs = ['stock' => [__('Stock'), $items->count()], 'purchases' => [__('Purchases'), $purchaseCount], 'issues' => [__('Moved out'), $issueCount]];
@endphp
<x-layouts.app :title="__('Inventory')">
    <x-page-head :title="__('Inventory')" :subtitle="__('Ink, plates, glue, parts and other stores: what was bought, what went out, and what is left.')">
        <x-slot:actions>
            @can('inventory.manage')
                <a class="btn" href="{{ route('inventory-categories.index') }}"><x-icon name="file" />{{ __('Categories') }}</a>
                <a class="btn" href="{{ route('inventory-items.create', array_filter(['category' => $categoryId])) }}"><x-icon name="plus" />{{ __('Add item') }}</a>
            @endcan
            @can('inventory.issue')
                <a class="btn" href="{{ route('inventory-issues.create') }}"><x-icon name="send" />{{ __('Move out') }}</a>
            @endcan
            @can('purchases.create')
                <a class="btn primary" href="{{ route('purchases.create') }}"><x-icon name="download" />{{ __('Add purchase') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($lowItems->isNotEmpty())
        <div class="alert"><x-icon name="alert" /><div>
            <b>{{ trans_choice(':count item is low or out of stock.|:count items are low or out of stock.', $lowItems->count()) }}</b>
            {{ $lowItems->map(fn ($i) => $i->localName().': '.InventoryItem::qty($i->balance()).' '.$i->unit)->join('; ') }}.
        </div></div>
    @endif

    <div class="grid g4">
        <x-kpi icon="box" :label="__('Items')" :foot="trans_choice(':count low|:count low', $lowItems->count())">{{ $items->where('is_active', true)->count() }}</x-kpi>
        <x-kpi icon="download" tone="good" :label="__('Purchased this month')" :foot="__('Approved and waiting')">Rs {{ number_format($monthTotal) }}</x-kpi>
        <x-kpi icon="clock" tone="warn" :label="__('Waiting for approval')" :foot="trans_choice(':count bill|:count bills', $pendingCount)">Rs {{ number_format($pendingTotal) }}</x-kpi>
        <x-kpi icon="send" :label="__('Moved out this month')" :foot="__('Entries')">{{ $issuedThisMonth }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs" role="tablist">
                @foreach ($tabs as $key => [$label, $count])
                    <a role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" @class(['tab', 'on' => $tab === $key]) style="text-decoration:none" href="{{ route('inventory.index', array_filter(['tab' => $key, 'category' => $categoryId])) }}">{{ $label }}<span class="c">{{ $count }}</span></a>
                @endforeach
            </div>
            <form class="filters" method="GET">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select class="select" name="category" aria-label="{{ __('Category') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->localName() }}</option>@endforeach
                </select>
            </form>
        </div>

        @if ($tab === 'stock')
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Category') }}</th><th class="r">{{ __('Bought') }}</th><th class="r">{{ __('Moved out') }}</th><th class="r">{{ __('Balance') }}</th><th class="r">{{ __('Reorder at') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $item)
                    @php $level = $item->level(); @endphp
                    <tr @if (! $item->is_active) style="opacity:.6" @endif>
                        <td><a class="main" href="{{ route('inventory-items.show', $item) }}" style="color:inherit;text-decoration:none">{{ $item->localName() }}</a>@unless ($item->is_active)<div class="meta">{{ __('Inactive') }}</div>@endunless</td>
                        <td>{{ $item->category->localName() }}</td>
                        <td class="r num">{{ InventoryItem::qty($item->stock_in) }}</td>
                        <td class="r num">{{ InventoryItem::qty($item->stock_out) }}</td>
                        <td class="r num"><b>{{ InventoryItem::qty($item->balance()) }}</b> <span class="meta">{{ $item->unit }}</span></td>
                        <td class="r num">{{ (float) $item->reorder_level ? InventoryItem::qty($item->reorder_level) : '—' }}</td>
                        <td><x-badge :tone="$levelTone[$level]">{{ $levelLabel[$level] }}</x-badge></td>
                        <td class="r" style="white-space:nowrap">
                            @if ($item->is_active)
                                @can('inventory.issue')@if ($item->balance() > 0)<a class="btn sm" href="{{ route('inventory-issues.create', ['item' => $item->id]) }}"><x-icon name="send" />{{ __('Move out') }}</a>@endif @endcan
                                @can('purchases.create')<a class="btn sm" href="{{ route('purchases.create', ['item' => $item->id]) }}" aria-label="{{ __('Add purchase') }}"><x-icon name="plus" /></a>@endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty"><b>{{ __('No items yet') }}</b>{{ __('Add an item, then record a purchase to start its stock.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        @elseif ($tab === 'purchases')
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($purchases as $p)
                    <tr>
                        <td><a class="main" href="{{ route('inventory-items.show', $p->item) }}" style="color:inherit;text-decoration:none">{{ $p->item->localName() }}</a><div class="meta">{{ $p->item->category->localName() }}</div></td>
                        <td>{{ $p->purchased_on->format('j M Y') }}<div class="meta">{{ $p->bill_no ? __('Bill :no', ['no' => $p->bill_no]) : '' }}</div></td>
                        <td>{{ $p->party->name }}</td>
                        <td class="r num">{{ InventoryItem::qty($p->quantity) }} {{ $p->item->unit }}</td>
                        <td class="r num">{{ number_format((float) $p->rate, 2) }}</td>
                        <td class="r num"><b>Rs {{ number_format($p->amount) }}</b></td>
                        <td><x-badge :tone="$p->status->tone()">{{ $p->status->label() }}</x-badge>@if ($p->review_note)<div class="meta">{{ $p->review_note }}</div>@endif</td>
                        <td class="r">@include('partials.attachment', ['path' => $p->attachment])</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty"><b>{{ __('No purchases yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $purchases->links() }}
        @else
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Qty') }}</th><th>{{ __('Sent to') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Note') }}</th></tr></thead>
                <tbody>
                @forelse ($issues as $i)
                    <tr>
                        <td><a class="main" href="{{ route('inventory-items.show', $i->item) }}" style="color:inherit;text-decoration:none">{{ $i->item->localName() }}</a><div class="meta">{{ $i->item->category->localName() }}</div></td>
                        <td>{{ $i->issued_on->format('j M Y') }}</td>
                        <td class="r num"><b>{{ InventoryItem::qty($i->quantity) }}</b> {{ $i->item->unit }}</td>
                        <td>{{ $i->issued_to }}@if ($i->machine)<div class="meta">{{ $i->machine->code }} {{ $i->machine->name }}</div>@endif</td>
                        <td>{{ $i->reference ?: '—' }}</td>
                        <td>{{ $i->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><b>{{ __('Nothing moved out yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $issues->links() }}
        @endif
    </div>
</x-layouts.app>
