<x-layouts.app :title="__('Paper stock')">
    <x-page-head :title="__('Paper stock')" :subtitle="__('Live rim balance for every size and party, from truck to press.')">
        <x-slot:actions>
            @can('godowns.manage')<a class="btn" href="{{ route('godowns.index') }}"><x-icon name="box" />{{ __('Godowns') }}</a>@endcan
            @can('paper.types')<a class="btn" href="{{ route('paper-types.index') }}"><x-icon name="paper" />{{ __('Paper types') }}</a>@endcan
            @can('paper.issue')<a class="btn" href="{{ route('paper-issues.create') }}"><x-icon name="send" />{{ __('Issue paper') }}</a>@endcan
            @can('paper.receive')<a class="btn primary" href="{{ route('paper-receipts.create') }}"><x-icon name="truck" />{{ __('Receive truck') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>

    <div class="grid g4">
        <x-kpi icon="paper" :label="__('Total balance')" :foot="__('1 rim = 500 sheets')">{{ number_format($balances->where('paper_type.unit', 'rims')->sum('balance')) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="truck" tone="good" :label="__('Received this week')" :foot="trans_choice(':count truck|:count trucks', $trucksThisWeek)">{{ number_format($receivedThisWeek) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="send" :label="__('Issued this week')" :foot="__('In-house, outsource and returns')">{{ number_format($issuedThisWeek) }}<small>{{ __('rims') }}</small></x-kpi>
        <x-kpi icon="alert" tone="warn" :label="__('Below reorder level')" :foot="$lowStock->map(fn ($r) => $r['paper_type']->name)->join(', ') ?: __('All sizes above reorder level')">{{ $lowStock->count() }}</x-kpi>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="tabs" role="tablist">
                @foreach (['stock' => [__('Stock by size'), $balances->count()], 'receipts' => [__('Truck receipts'), $receipts->total()], 'issues' => [__('Issued'), $issues->total()]] as $key => [$label, $count])
                    <a role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" @class(['tab', 'on' => $tab === $key]) style="text-decoration:none" href="{{ route('paper.index', ['tab' => $key] + array_filter($filters)) }}">{{ $label }}<span class="c">{{ $count }}</span></a>
                @endforeach
            </div>
            <form class="filters" method="GET">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select class="select" name="paper_type_id" aria-label="{{ __('Paper') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All paper') }}</option>
                    @foreach ($paperTypes as $type)<option value="{{ $type->id }}" @selected($filters['paper_type_id'] == $type->id)>{{ $type->code }} — {{ $type->label() }}</option>@endforeach
                </select>
                <select class="select" name="party_id" aria-label="{{ __('Party') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All parties') }}</option>
                    @foreach ($parties as $party)<option value="{{ $party->id }}" @selected($filters['party_id'] == $party->id)>{{ $party->name }}</option>@endforeach
                </select>
            </form>
        </div>

        @if ($tab === 'stock')
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Paper') }}</th><th>{{ __('Party') }}</th><th class="r">{{ __('Received') }}</th><th class="r">{{ __('Issued') }}</th><th class="r">{{ __('Balance') }}</th><th style="min-width:150px">{{ __('Level') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($balances as $row)
                    @php
                        $type = $row['paper_type'];
                        $pct = $row['received'] ? $row['balance'] / $row['received'] * 100 : 0;
                        $tone = ['ok' => 'good', 'low' => 'warn', 'critical' => 'bad'][$row['status']];
                    @endphp
                    <tr>
                        <td><div class="main">{{ $type->label() }}</div><div class="meta">{{ $type->code }}</div></td>
                        <td><div class="who"><span class="avatar sm">{{ mb_strtoupper(mb_substr($row['party']->name, 0, 2)) }}</span>{{ $row['party']->name }}</div></td>
                        <td class="r num">{{ number_format($row['received']) }}@if ($row['pending'])<div class="meta">{{ __(':n waiting', ['n' => number_format($row['pending'])]) }}</div>@endif</td>
                        <td class="r num">{{ number_format($row['issued']) }}</td>
                        <td class="r num"><b>{{ number_format($row['balance']) }}</b> <span class="meta">{{ __($type->unit) }}</span></td>
                        <td><div @class(['meter', 'low' => $row['status'] === 'low', 'crit' => $row['status'] === 'critical'])><i style="width:{{ max(2, min(100, $pct)) }}%"></i></div></td>
                        <td><x-badge :tone="$tone">{{ ['ok' => __('OK'), 'low' => __('Low'), 'critical' => __('Critical')][$row['status']] }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><b>{{ __('No paper on record yet') }}</b>{{ __('Receive the first truck to start the running balance.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        @elseif ($tab === 'receipts')
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Slip') }}</th><th>{{ __('Date') }}</th><th>{{ __('Party') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Truck') }}</th><th class="r">{{ __('Quantity') }}</th><th>{{ __('Received by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($receipts as $r)
                    <tr>
                        <td><b>{{ __('No.') }} {{ $r->slip_no }}</b></td>
                        <td>{{ $r->received_at->format('j M Y') }}<div class="meta">{{ $r->received_at->format('H:i') }}</div></td>
                        <td>{{ $r->party->name }}</td>
                        <td>{{ $r->paperType->name }}<div class="meta">{{ $r->paperType->size }}</div></td>
                        <td>@if ($r->truck_no)<span class="tag">{{ $r->truck_no }}</span>@endif<div class="meta">{{ $r->godown?->localName() }}</div></td>
                        <td class="r num"><b>{{ number_format($r->quantity) }}</b></td>
                        <td>{{ $r->received_by }}</td>
                        <td><x-badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-badge></td>
                        <td class="r"><a class="btn sm" href="{{ route('paper-receipts.show', $r) }}"><x-icon name="eye" />{{ __('View slip') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="empty"><b>{{ __('No trucks received yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $receipts->links() }}
        @else
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Party') }}</th><th>{{ __('Sent to') }}</th><th class="r">{{ __('Rims out') }}</th></tr></thead>
                <tbody>
                @forelse ($issues as $i)
                    <tr>
                        <td>{{ $i->issued_on->format('j M Y') }}</td>
                        <td><x-badge :tone="$i->kind->tone()">{{ $i->kind->label() }}</x-badge></td>
                        <td><div class="main">{{ $i->reference ?: '—' }}</div><div class="meta">{{ $i->book_name }}@if ($i->copies) · {{ __(':n copies', ['n' => number_format($i->copies)]) }}@endif</div></td>
                        <td>{{ $i->paperType->code }}<div class="meta">{{ $i->paperType->label() }}</div></td>
                        <td>{{ $i->party->name }}</td>
                        <td>{{ $i->issued_to }}</td>
                        <td class="r num"><b>{{ number_format($i->quantity) }}</b>@if ((float) $i->wastage_percent > 0)<div class="meta">{{ __('incl. :p% wastage', ['p' => rtrim(rtrim($i->wastage_percent, '0'), '.')]) }}</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><b>{{ __('No paper issued yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $issues->links() }}
        @endif
    </div>
</x-layouts.app>
