<x-layouts.app :title="__('Plates')">
    <x-page-head :title="__('Plate processing')" :subtitle="__('Plates made by processors, and which bills are cleared.')">
        <x-slot:actions><a class="btn" href="{{ route('processors.index') }}"><x-icon name="users" />{{ __('Processors') }}</a>@can('plates.manage')<a class="btn primary" href="{{ route('plate-jobs.create') }}"><x-icon name="plus" />{{ __('Add plate job') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    <div class="grid g3">
        <x-kpi icon="alert" tone="warn" :label="__('Unpaid plate bills')">Rs {{ number_format($unpaidTotal) }}</x-kpi>
        <x-kpi icon="file" :label="__('Plates this month')">{{ number_format($monthPlates) }}</x-kpi>
        <x-kpi icon="wallet" :label="__('Plate cost this month')">Rs {{ number_format($monthAmount) }}</x-kpi>
    </div>
    <div class="card">
        <div class="toolbar" style="justify-content:space-between;flex-wrap:wrap;gap:8px"><div class="tabs">
            @foreach (['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'all' => __('All')] as $k => $l)
                <a href="{{ route('plate-jobs.index', ['show' => $k]) }}" @class(['tab', 'on' => $show === $k]) style="text-decoration:none">{{ $l }}</a>
            @endforeach
        </div>
            @can('plates.manage')@if ($unpaidTotal > 0)
                <form method="POST" action="{{ route('plate-jobs.pay-all') }}" class="inline-form" style="display:inline-flex;gap:6px;align-items:center" onsubmit="return confirm(@js(__('Pay all unpaid plate bills (Rs :n)?', ['n' => number_format($unpaidTotal)])))">@csrf
                    <input class="select" style="width:140px" type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Paid on') }}">
                    <button class="btn sm good" type="submit"><x-icon name="check" />{{ __('Pay all (Rs :n)', ['n' => number_format($unpaidTotal)]) }}</button>
                </form>
            @endif @endcan
        </div>
        @include('plate-jobs._table', ['jobs' => $jobs, 'withProcessor' => true])
        {{ $jobs->links() }}
    </div>
</x-layouts.app>
