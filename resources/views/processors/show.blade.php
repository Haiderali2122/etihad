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

    @can('plates.manage')@if ($billed > $paid)
        {{-- Any amount: it clears the oldest plate bills first; the rest stays unpaid. --}}
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Pay :name', ['name' => $processor->name]) }}</h3><div class="sub">{{ __("Give any amount; it clears the oldest bills first and the rest stays unpaid. It comes off the manager's cash.") }}</div></div></div>
            <div class="card-b">
                <form method="POST" action="{{ route('processors.pay', $processor) }}" class="form">@csrf
                    <x-field name="amount" type="number" min="1" step="1" :max="$billed - $paid" :label="__('Amount (Rs)')" :value="$billed - $paid" required />
                    <x-field name="paid_on" type="date" :label="__('Paid on')" :value="today()->toDateString()" required />
                    <x-field name="note" :label="__('Note')" />
                    <div class="field full" style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="btn primary" type="submit"><x-icon name="check" />{{ __('Pay') }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('plate-jobs.pay-all', ['processor' => $processor->id]) }}" style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap" onsubmit="return confirm(@js(__('Pay all Rs :n?', ['n' => number_format($billed - $paid)])))">@csrf
                    <input class="select" style="width:140px" type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Paid on') }}">
                    <button class="btn good" type="submit"><x-icon name="check" />{{ __('Pay all (Rs :n)', ['n' => number_format($billed - $paid)]) }}</button>
                </form>
            </div>
        </div>
    @endif @endcan

    <div class="card">
        <div class="card-h"><div><h3>{{ __('Plates and payments') }}</h3><div class="sub">{{ __('The same records as the Plates tab.') }}</div></div></div>
        @include('plate-jobs._table', ['jobs' => $jobs, 'withProcessor' => false])
    </div>
</x-layouts.app>
