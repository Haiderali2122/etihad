<x-layouts.app :title="__('Cash-out')">
    <x-page-head :title="__('Cash-out')" :subtitle="__('Cash given by the owner and where each rupee was spent.')">
        <x-slot:actions>@can('expense-heads.manage')<a class="btn" href="{{ route('expense-heads.index') }}"><x-icon name="file" />{{ __('Expense heads') }}</a>@endcan @can('expenses.create')<a class="btn primary" href="{{ route('expenses.create') }}"><x-icon name="plus" />{{ __('Add expense') }}</a>@endcan</x-slot:actions>
    </x-page-head>

    <div class="grid g4">
        <x-kpi icon="wallet" tone="warn" :label="__('Cash with manager')" :foot="__('Given minus spent from it')">Rs {{ number_format($cashGiven - $cashSpent) }}</x-kpi>
        <x-kpi icon="download" :label="__('Total given')">Rs {{ number_format($cashGiven) }}</x-kpi>
        <x-kpi icon="send" :label="__('Total spent')" :foot="__('Rs :cash from manager\'s cash · Rs :owner paid by owner', ['cash' => number_format($cashSpent), 'owner' => number_format($totalSpent - $cashSpent)])">Rs {{ number_format($totalSpent) }}</x-kpi>
        <x-kpi icon="shield" tone="good" :label="__('Spent today')" :foot="$dailyLimit ? __('Manager Rs :n of daily limit Rs :limit', ['n' => number_format($todayFromCash), 'limit' => number_format($dailyLimit)]) : __('No daily limit set — every spend needs approval')">Rs {{ number_format($todaySpent) }}</x-kpi>
    </div>

    {{-- Where the money went: search one thing (e.g. nut bolts) across all receipts, or pick from the list, biggest first. --}}
    <div class="card" id="spending">
        <div class="card-h"><div><h3>{{ __('Where the money went') }}</h3><div class="sub">{{ __('Search any item, head or person paid. Each receipt line counts on its own, even on receipts mixed with other expenses.') }}</div></div></div>
        <div class="card-b">
            <form method="GET" action="{{ route('expenses.index') }}#spending" id="spendForm" style="display:flex;flex-direction:column;gap:10px">
                @if ($headId)<input type="hidden" name="head" value="{{ $headId }}">@endif
                <div style="display:flex;gap:8px">
                    <input class="select" type="search" name="q" id="spendSearch" value="{{ $search }}" list="spentOnList" placeholder="{{ __('Search e.g. nut bolts') }}" aria-label="{{ __('Search spending') }}" style="flex:1;min-width:0;height:46px;font-size:16px;padding:0 14px">
                    <datalist id="spentOnList">@foreach ($spentOn as $row)<option value="{{ $row['name'] }}">@endforeach</datalist>
                    <button class="btn primary" type="submit" style="height:46px"><x-icon name="search" />{{ __('Search') }}</button>
                </div>
                <div class="filters" style="flex-wrap:wrap;gap:8px">
                    <select class="select" name="pick" aria-label="{{ __('Spent on, biggest first') }}" onchange="if (this.value) { this.form.q.value = this.value; this.form.submit(); }">
                        <option value="">{{ __('Spent on (biggest first)') }}</option>
                        @foreach ($spentOn as $row)<option value="{{ $row['name'] }}">{{ $row['name'] }} — Rs {{ number_format($row['total']) }}</option>@endforeach
                    </select>
                    <select class="select" name="period" aria-label="{{ __('Period') }}" onchange="document.getElementById('spendDates').hidden = this.value !== 'custom'; if (this.value !== 'custom') { this.form.submit(); }">
                        @foreach ($periods as $k => $l)<option value="{{ $k }}" @selected($period === $k)>{{ $l }}</option>@endforeach
                    </select>
                    <span id="spendDates" @if ($period !== 'custom') hidden @endif style="display:inline-flex;gap:6px">
                        <input class="select" type="date" name="from" value="{{ $period === 'custom' ? $from : '' }}" aria-label="{{ __('From') }}">
                        <input class="select" type="date" name="to" value="{{ $period === 'custom' ? $to : '' }}" aria-label="{{ __('To') }}">
                    </span>
                    @if ($searching)<a class="btn" href="{{ route('expenses.index', ['period' => $period, 'from' => $period === 'custom' ? $from : null, 'to' => $period === 'custom' ? $to : null]) }}#spending">{{ __('Clear') }}</a>@endif
                </div>
            </form>
        </div>
        @if ($searching)
            <div class="card-b" style="padding-top:0"><b>{{ __(':n entries for ":q": Rs :total', ['n' => $found->count(), 'q' => $search, 'total' => number_format($foundTotal)]) }}</b> <span class="meta">{{ $periods[$period] }}@if ($from) · {{ \Illuminate\Support\Carbon::parse($from)->format('j M Y') }}@if ($to && $to !== $from) – {{ \Illuminate\Support\Carbon::parse($to)->format('j M Y') }}@endif @endif</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Item') }}</th><th>{{ __('Head') }}</th><th>{{ __('Paid to') }}</th><th class="r">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($found as $l)
                    <tr data-href="{{ route('records.show', ['expense', $l['expense']->id]) }}">
                        <td>{{ $l['date']->format('j M Y') }}</td>
                        <td>{{ $l['description'] ?: $l['name'] }}@if ($l['quantity'] !== null && $l['rate'] !== null)<div class="meta">{{ rtrim(rtrim((string) $l['quantity'], '0'), '.') }} × {{ number_format((float) $l['rate'], 2) }}</div>@endif</td>
                        <td>{{ $l['head'] }}</td>
                        <td>{{ $l['paid_to'] }}</td>
                        <td class="r num"><b>Rs {{ number_format($l['amount']) }}</b></td>
                        <td class="r"><a class="btn sm" href="{{ route('expenses.show', $l['expense']) }}"><x-icon name="printer" />{{ $l['expense']->receiptNumber() }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><b>{{ __('Nothing found') }}</b>{{ __('Try another word or a longer period.') }}</div></td></tr>
                @endforelse
                </tbody>
                @if ($found->isNotEmpty())<tfoot><tr><td colspan="4" style="font-weight:700">{{ __('Total') }}</td><td class="r num"><b>Rs {{ number_format($foundTotal) }}</b></td><td></td></tr></tfoot>@endif
            </table></div>
        @else
            {{-- The biggest spending in the period, shown once the search box is clicked; click a row to see its entries. --}}
            <div class="tbl-wrap" id="spendTop" hidden><table>
                <thead><tr><th>{{ __('Spent on') }}</th><th class="r">{{ __('Entries') }}</th><th class="r">{{ __('Total') }}</th></tr></thead>
                <tbody>
                @forelse (array_slice($spentOn, 0, 10) as $row)
                    <tr><td><a class="main" href="{{ route('expenses.index', ['q' => $row['name'], 'period' => $period, 'from' => $period === 'custom' ? $from : null, 'to' => $period === 'custom' ? $to : null]) }}#spending">{{ $row['name'] }}</a></td><td class="r num">{{ $row['count'] }}</td><td class="r num"><b>Rs {{ number_format($row['total']) }}</b></td></tr>
                @empty
                    <tr><td colspan="3"><div class="empty"><b>{{ __('Nothing spent in this period') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="grid g-main">
        <div class="card">
            <div class="toolbar">
                <h3 style="margin:0">{{ __('Roznamcha') }}</h3>
                <form class="filters" method="GET">
                    <select class="select" name="head" aria-label="{{ __('Head') }}" onchange="this.form.submit()">
                        <option value="">{{ __('All heads') }}</option>
                        @foreach ($heads as $h)<option value="{{ $h->id }}" @selected($headId == $h->id)>{{ $h->localName() }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Head') }}</th><th>{{ __('Paid to') }}</th><th>{{ __('Reason') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr data-href="{{ route('records.show', ['expense', $e->id]) }}">
                        <td>{{ $e->spent_on->format('j M') }}<div class="meta">{{ $e->user->name }}</div></td>
                        @php $lineHeads = $e->items->map(fn ($i) => $i->head ?? $e->head)->unique('id')->values(); @endphp
                        <td>@foreach ($lineHeads->isNotEmpty() ? $lineHeads : collect([$e->head]) as $h)<span class="who"><i style="width:10px;height:10px;border-radius:3px;background:{{ $h->color }};display:inline-block"></i>{{ $h->localName() }}</span>@endforeach</td>
                        <td>{{ $e->paid_to }}</td>
                        <td>
                            @if ($e->isLinked() && $e->source)
                                <a href="{{ $e->source->cashOutUrl() }}">{{ $e->source->cashOutSource() }}</a><div class="meta">{{ $e->reason }}</div>
                            @else
                                {{ $e->reason }}@if ($e->items->count() > 1)<div class="meta">{{ trans_choice(':count payment|:count payments', $e->items->count()) }}</div>@endif
                            @endif
                            @if ($e->machine)<div class="meta">{{ $e->machine->code }}</div>@endif
                        </td>
                        <td class="r num"><b>Rs {{ number_format($e->amount) }}</b></td>
                        <td><x-badge :tone="$e->status->tone()">{{ $e->status->label() }}</x-badge>@if ($e->review_note)<div class="meta">{{ $e->review_note }}</div>@endif</td>
                        <td class="r" style="white-space:nowrap">@include('partials.attachment', ['path' => $e->attachment])<a class="btn sm" href="{{ route('expenses.show', $e) }}"><x-icon name="printer" />{{ __('Receipt') }}</a>@can('expenses.create')
                            <x-delete-button :action="route('expenses.destroy', $e)" :confirm="$e->isLinked() && $e->source ? __('Delete this :kind? It is also removed from its own tab.', ['kind' => mb_strtolower($e->source->cashOutSource())]) : __('Delete expense :no?', ['no' => $e->receiptNumber()])" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><b>{{ __('No expenses yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $expenses->links() }}
        </div>

        <div style="display:flex;flex-direction:column;gap:18px">
            @can('give-cash')
                <form class="card" method="POST" action="{{ route('cash-floats.store') }}">
                    @csrf
                    <div class="card-h"><div><h3>{{ __('Give cash to manager') }}</h3><div class="sub">{{ __('Recorded against the manager\'s cash in hand') }}</div></div></div>
                    <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                        <x-field name="user_id" :label="__('Manager')" :options="$managers->pluck('name', 'id')" required />
                        <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" required />
                        <x-field name="given_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                        <x-field name="note" :label="__('Note')" />
                        <button class="btn primary" type="submit" style="justify-content:center"><x-icon name="wallet" />{{ __('Record cash given') }}</button>
                    </div></div>
                </form>
            @endcan
            <div class="card">
                <div class="card-h"><div><h3>{{ __('Cash given') }}</h3><div class="sub">{{ __('Latest 10') }}</div></div></div>
                <div class="card-b"><div class="list">
                    @forelse ($floats as $f)
                        <div class="row"><span class="ic-tile good"><x-icon name="wallet" /></span><div class="grow"><div class="t">{{ $f->user->name }}</div><div class="s">{{ $f->given_on->format('j M Y') }}{{ $f->note ? ' — '.$f->note : '' }}</div></div><b class="num">Rs {{ number_format($f->amount) }}</b>@can('give-cash')<x-delete-button :action="route('records.destroy', ['cash-float', $f->id])" :label="''" small aria-label="{{ __('Delete') }}" :danger="false" />@endcan</div>
                    @empty
                        <div class="empty"><b>{{ __('No cash given yet') }}</b></div>
                    @endforelse
                </div></div>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            (() => {
                // Clicking the search box opens the list of what the money went on, biggest first.
                const box = document.getElementById('spendSearch'), top = document.getElementById('spendTop');
                if (!box || !top) { return; }
                const open = () => { top.hidden = false; };
                box.addEventListener('focus', open);
                box.addEventListener('click', open);
            })();
        </script>
    @endpush
</x-layouts.app>
