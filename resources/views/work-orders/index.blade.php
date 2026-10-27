<x-layouts.app :title="__('Work orders')">
    <x-page-head :title="__('Work orders')" :subtitle="__('Every order from approval to dispatch. Paper leaves the godown when an order is approved.')">
        <x-slot:actions>
            @can('orders.manage')<a class="btn primary" href="{{ route('work-orders.create') }}"><x-icon name="plus" />{{ __('New work order') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>

    <div class="grid g3">
        <x-kpi icon="orders" :label="__('In production')" :foot="__(':n copies', ['n' => number_format($inProduction->sum('copies'))])">{{ $inProduction->count() }}</x-kpi>
        <x-kpi icon="clock" tone="warn" :label="__('Waiting approval')">{{ $columns['waiting']->count() }}</x-kpi>
        <x-kpi icon="alert" tone="bad" :label="__('Past due date')">{{ $overdue }}</x-kpi>
    </div>

    <div class="board">
        @foreach (\App\Enums\WorkOrderStage::cases() as $stage)
            <div class="col">
                <div class="col-h"><span>{{ $stage->label() }}</span><span class="c">{{ $columns[$stage->value]->count() }}</span></div>
                @forelse ($columns[$stage->value] as $order)
                    <a class="job" href="{{ route('work-orders.show', $order) }}" style="color:inherit;text-decoration:none">
                        <span class="id">{{ $order->number }}</span>
                        <span class="bk">{{ $order->bookFormat->book->name }}</span>
                        <span class="facts">
                            <span class="tag">{{ number_format($order->copies) }} {{ __('copies') }}</span>
                            <span class="tag">{{ number_format($order->rims_needed) }} {{ __('rims') }}</span>
                            <x-badge :tone="$order->mode->tone()">{{ $order->mode->label() }}</x-badge>
                        </span>
                        <span class="foot">
                            <span>{{ $order->printedAt() }}</span>
                            @if ($order->due_on)<span class="due" @style(['color:var(--bad);font-weight:600' => $order->due_on->isPast() && $stage !== \App\Enums\WorkOrderStage::Dispatched])><x-icon name="cal" />{{ __('Due :date', ['date' => $order->due_on->format('j M')]) }}</span>@endif
                        </span>
                    </a>
                @empty
                    <div class="empty" style="padding:18px 6px;font-size:13px">{{ __('None') }}</div>
                @endforelse
            </div>
        @endforeach
    </div>

    @if ($rejected->isNotEmpty())
        <div class="card"><div class="card-h"><div><h3>{{ __('Sent back') }}</h3></div></div><div class="card-b"><div class="list">
            @foreach ($rejected as $r)<a class="row" href="{{ route('work-orders.show', $r) }}" style="color:inherit;text-decoration:none"><span class="grow"><span class="t">{{ $r->number }}</span><span class="s"> · {{ $r->review_note }}</span></span><x-badge tone="bad">{{ __('Sent back') }}</x-badge></a>@endforeach
        </div></div></div>
    @endif
</x-layouts.app>
