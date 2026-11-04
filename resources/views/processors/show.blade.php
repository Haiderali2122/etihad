<x-layouts.app :title="$processor->name">
    <x-page-head :back="route('processors.index')" :title="$processor->name" :subtitle="collect([$processor->phone, $processor->address])->filter()->join(' · ')">
        <x-slot:actions>
            @can('plates.manage')
                <a class="btn" href="{{ route('processors.edit', $processor) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                <x-delete-button :action="route('processors.destroy', $processor)" :confirm="__('Delete :n?', ['n' => $processor->name])" :label="__('Delete')" />
                <a class="btn primary" href="{{ route('plate-jobs.create', ['processor' => $processor->id]) }}"><x-icon name="plus" />{{ __('Add plate job') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g4">
        <x-kpi icon="file" :label="__('Plates given')" :foot="__(':n returned', ['n' => number_format($returned)])">{{ number_format($given) }}</x-kpi>
        <x-kpi icon="box" tone="warn" :label="__('Still with processor')">{{ number_format($given - $returned) }}</x-kpi>
        <x-kpi icon="check" tone="good" :label="__('Paid')" :foot="__('of Rs :n billed', ['n' => number_format($billed)])">Rs {{ number_format($paid) }}</x-kpi>
        <x-kpi icon="wallet" :tone="$billed > $paid ? 'bad' : 'good'" :label="__('Still to pay')">Rs {{ number_format($billed - $paid) }}</x-kpi>
    </div>

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Plates and payments') }}</h3><div class="sub">{{ __('The same records as the Plates tab.') }}</div></div></div>
        @include('plate-jobs._table', ['jobs' => $jobs, 'withProcessor' => false])
    </div>
</x-layouts.app>
