@php
    $sections = [
        'purchases_by_supplier' => __('Purchases by supplier'),
        'purchases_by_category' => __('Purchases by item category'),
        'expenses_by_head' => __('Cash spent by head'),
        'machine_spend' => __('Machine repair spend'),
        'paper_movement' => __('Paper in and out'),
    ];
@endphp
<x-layouts.app :title="__('Reports')">
    <x-page-head :title="__('Reports')" :subtitle="__('Approved entries for the period. Each report exports to Excel (CSV).')">
        <x-slot:actions>
            <form method="GET" class="filters" style="align-items:center">
                <input class="select" type="date" name="from" value="{{ $from->toDateString() }}" aria-label="{{ __('From') }}">
                <span class="muted">–</span>
                <input class="select" type="date" name="to" value="{{ $to->toDateString() }}" aria-label="{{ __('To') }}">
                <button class="btn primary" type="submit">{{ __('Show') }}</button>
            </form>
        </x-slot:actions>
    </x-page-head>
    <div class="grid g-half">
        @foreach ($sections as $key => $label)
            @php $rows = $$key; @endphp
            <div class="card">
                <div class="card-h"><div><h3>{{ $label }}</h3><div class="sub">{{ $from->format('j M') }} – {{ $to->format('j M Y') }}</div></div>
                    <a class="btn sm" href="{{ route('reports.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'export' => $key]) }}"><x-icon name="download" />{{ __('Export') }}</a></div>
                <div class="card-b" style="padding-inline:0">
                    @if ($rows->isEmpty())
                        <div class="empty">{{ __('Nothing in this period.') }}</div>
                    @else
                        <div class="tbl-wrap"><table>
                            <thead><tr>@foreach (array_keys($rows->first()) as $i => $col)<th @class(['r' => $i > 0])>{{ $col }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($rows as $row)<tr>@foreach (array_values($row) as $i => $val)<td @class(['r num' => $i > 0])>{{ is_int($val) ? number_format($val) : $val }}</td>@endforeach</tr>@endforeach</tbody>
                            <tfoot><tr>@foreach (array_keys($rows->first()) as $i => $col)<td @class(['r num' => $i > 0])>{{ $i === 0 ? __('Total') : number_format($rows->sum($col)) }}</td>@endforeach</tr></tfoot>
                        </table></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
