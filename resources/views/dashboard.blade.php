<x-layouts.app :title="__('Dashboard')">
    <x-page-head :title="__('Good day, :name', ['name' => auth()->user()->name])" :subtitle="__('Here is the press today, :date.', ['date' => now()->translatedFormat('l j F Y')])">
        <x-slot:actions>
            @can('reports.view')<a class="btn" href="{{ route('reports.index') }}"><x-icon name="chart" />{{ __('Reports') }}</a>@endcan
            @can('expenses.create')<a class="btn" href="{{ route('expenses.create') }}"><x-icon name="wallet" />{{ __('Add expense') }}</a>@endcan
            @can('paper.receive')<a class="btn primary" href="{{ route('paper-receipts.create') }}"><x-icon name="truck" />{{ __('Receive truck') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>

    @if ($lowStock->isNotEmpty())
        <div class="alert"><x-icon name="alert" /><div>
            <b>{{ trans_choice(':count paper size is below its reorder level.|:count paper sizes are below their reorder level.', $lowStock->count()) }}</b>
            {{ $lowStock->map(fn ($row) => $row['paper_type']->label().': '.number_format($row['balance']).' '.__($row['paper_type']->unit))->join('; ') }}.
            @can('paper.view')<a href="{{ route('paper.index') }}" style="color:#4D3000;font-weight:700">{{ __('Review paper stock') }}</a>@endcan
        </div></div>
    @endif

    <div class="grid g4">
        <x-kpi icon="paper" :label="__('Paper in godown')" :foot="__(':n sizes, :low low', ['n' => $paperTypeCount, 'low' => $lowStock->count()])">
            {{ number_format($paperBalance) }}<small>{{ __('rims') }}</small>
        </x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Cash with manager')" :foot="__('Rs :spent spent of Rs :given given', ['spent' => number_format($cashSpent), 'given' => number_format($cashGiven)])">
            Rs {{ number_format($cashInHand) }}
        </x-kpi>
        <x-kpi icon="wrench" tone="bad" :label="__('Machine spend, :month', ['month' => now()->translatedFormat('F')])" :foot="trans_choice(':count machine under repair|:count machines under repair', $machinesUnderRepair)">
            Rs {{ number_format($machineSpend) }}
        </x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Waiting for approval')" :foot="__('Receipts, bills, repairs and expenses')">
            {{ $pending->count() }}
        </x-kpi>
    </div>

    <div class="grid g-main">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Paper received and used') }}</h3><div class="sub">{{ __('Rims per week, last 8 weeks') }}</div></div>
                <div class="legend"><span><i style="background:#262B7E"></i>{{ __('Received') }}</span><span><i style="background:#9DB6E6"></i>{{ __('Issued') }}</span></div></div>
            <div class="card-b"><div class="chart-box"><canvas id="cPaper" aria-label="{{ __('Paper received and used per week') }}"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Waiting for you') }}</h3><div class="sub">{{ trans_choice(':count approval|:count approvals', $pending->count()) }}</div></div>
                @can('approve')<a class="btn sm" href="{{ route('approvals.index') }}">{{ __('See all') }}</a>@endcan</div>
            <div class="card-b"><div class="list">
                @forelse ($pending->take(5) as $item)
                    @php $m = $item['model']; @endphp
                    <div class="row"><span @class(['ic-tile', 'warn' => $m->approvalAmount()])><x-icon :name="$m->approvalIcon()" /></span>
                        <div class="grow"><div class="t">{{ $m->approvalTitle() }}</div><div class="s">{{ $m->approvalKind() }}, {{ $m->created_at->diffForHumans() }}</div></div>
                        @if ($m->approvalAmount())<b class="num">Rs {{ number_format($m->approvalAmount()) }}</b>@endif
                    </div>
                @empty
                    <div class="empty"><b>{{ __('All clear') }}</b>{{ __('No entries are waiting.') }}</div>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="grid g3">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Where the cash went') }}</h3><div class="sub">{{ __('This month') }}</div></div></div>
            <div class="card-b">
                @if (count($heads))
                    <div class="donut-wrap"><div class="chart-box sm" style="height:140px"><canvas id="cHeads" aria-label="{{ __('Spend by expense head') }}"></canvas></div>
                        <div class="lg-list">@foreach (array_slice($heads, 0, 6) as $h)<div><i style="background:{{ $h['color'] }}"></i><span>{{ $h['name'] }}</span><b class="num">Rs {{ number_format($h['amount']) }}</b></div>@endforeach</div></div>
                @else
                    <div class="empty"><b>{{ __('Nothing spent yet') }}</b>{{ __('Expenses this month will show here.') }}</div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Machines') }}</h3><div class="sub">{{ __(':n of :total running', ['n' => $machines->where('status', \App\Enums\MachineStatus::Running)->count(), 'total' => $machines->count()]) }}</div></div>
                @can('machines.view')<a class="btn sm" href="{{ route('machines.index') }}">{{ __('Open') }}</a>@endcan</div>
            <div class="card-b"><div class="list">
                @php $canOpenMachines = auth()->user()->can('machines.view'); @endphp
                @foreach ($machines->take(6) as $machine)
                    @if ($canOpenMachines)<a class="row" href="{{ route('machines.show', $machine) }}" style="color:inherit;text-decoration:none">@else<div class="row">@endif
                        <span @class(['ic-tile', 'good' => $machine->status->value === 'running', 'bad' => $machine->status->value === 'under_repair'])><x-icon :name="$machine->type->value === 'printing' ? 'printer' : 'book'" /></span>
                        <div class="grow"><div class="t">{{ $machine->code }} {{ $machine->name }}</div><div class="s">{{ $machine->type->label() }}</div></div>
                        <x-badge :tone="$machine->status->tone()">{{ $machine->status->label() }}</x-badge>
                    @if ($canOpenMachines)</a>@else</div>@endif
                @endforeach
            </div></div>
        </div>
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Today at the press') }}</h3><div class="sub">{{ __('Latest activity') }}</div></div></div>
            <div class="card-b"><div class="feed">
                @forelse ($feed as $f)
                    <div class="it"><span class="pt {{ $f['tone'] }}"></span><div><div class="tx">{!! $f['text'] !!}</div><div class="tm">{{ $f['at']->diffForHumans() }}</div></div></div>
                @empty
                    <div class="empty"><b>{{ __('Quiet so far') }}</b></div>
                @endforelse
            </div></div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
            Chart.defaults.font.family = 'Figtree, system-ui, sans-serif';
            Chart.defaults.color = '#5E6487';
            const weekly = @json($weekly);
            new Chart(document.getElementById('cPaper'), {
                type: 'bar',
                data: { labels: weekly.labels, datasets: [
                    { label: @json(__('Received')), data: weekly.received, backgroundColor: '#262B7E', borderRadius: 6, maxBarThickness: 22 },
                    { label: @json(__('Issued')), data: weekly.issued, backgroundColor: '#9DB6E6', borderRadius: 6, maxBarThickness: 22 },
                ] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#EEF0F6' }, border: { display: false } } } },
            });
            const heads = @json($heads);
            if (heads.length && document.getElementById('cHeads')) {
                new Chart(document.getElementById('cHeads'), {
                    type: 'doughnut',
                    data: { labels: heads.map(h => h.name), datasets: [{ data: heads.map(h => h.amount), backgroundColor: heads.map(h => h.color), borderWidth: 2, borderColor: '#fff' }] },
                    options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } },
                });
            }
        </script>
    @endpush
</x-layouts.app>
