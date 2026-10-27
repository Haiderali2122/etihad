<x-layouts.app :title="__('Plates')">
    <x-page-head :title="__('Plate processing')" :subtitle="__('Plates made by processors, and which bills are cleared.')">
        <x-slot:actions>@can('plates.manage')<a class="btn primary" href="{{ route('plate-jobs.create') }}"><x-icon name="plus" />{{ __('Add plate job') }}</a>@endcan</x-slot:actions>
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
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Processor') }}</th><th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th class="r">{{ __('Plates') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Payment') }}</th></tr></thead>
            <tbody>
            @forelse ($jobs as $j)
                <tr>
                    <td><b>{{ $j->processor->name }}</b><div class="meta">{{ $j->note }}</div></td>
                    <td>{{ $j->job_on->format('j M Y') }}</td>
                    <td>@if ($j->workOrder)<a href="{{ route('work-orders.show', $j->workOrder) }}">{{ $j->workOrder->number }}</a>@else — @endif</td>
                    <td class="r num">{{ number_format($j->plates) }}</td>
                    <td class="r num">{{ number_format((float) $j->rate, 2) }}</td>
                    <td class="r num"><b>Rs {{ number_format($j->amount) }}</b></td>
                    <td>
                        @if ($j->isPaid())
                            <x-badge tone="good">{{ __('Paid :date', ['date' => $j->paid_on->format('j M')]) }}</x-badge>@if ($j->payment_note)<div class="meta">{{ $j->payment_note }}</div>@endif
                        @else
                            @can('plates.manage')
                                <form method="POST" action="{{ route('plate-jobs.pay', $j) }}" class="inline-form">@csrf @method('PATCH')
                                    <input type="hidden" name="paid_on" value="{{ today()->toDateString() }}">
                                    <button class="btn sm good" type="submit"><x-icon name="check" />{{ __('Mark paid') }}</button>
                                </form>
                            @else<x-badge tone="warn">{{ __('Unpaid') }}</x-badge>@endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><b>{{ __('No plate jobs here') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $jobs->links() }}
    </div>
</x-layouts.app>
