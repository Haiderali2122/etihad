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
        <div class="toolbar"><div class="tabs">
            @foreach (['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'all' => __('All')] as $k => $l)
                <a href="{{ route('plate-jobs.index', ['show' => $k]) }}" @class(['tab', 'on' => $show === $k]) style="text-decoration:none">{{ $l }}</a>
            @endforeach
        </div></div>
        @include('plate-jobs._table', ['jobs' => $jobs, 'withProcessor' => true])
        {{ $jobs->links() }}
    </div>
</x-layouts.app>
