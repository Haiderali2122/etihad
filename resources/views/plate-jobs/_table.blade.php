{{-- Plate records with their returns and payment. Used on the Plates tab and on each processor's page, so both show the same records. --}}
<div class="tbl-wrap"><table>
    <thead><tr>
        @if ($withProcessor)<th>{{ __('Processor') }}</th>@endif
        <th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th class="r">{{ __('Given') }}</th><th class="r">{{ __('Returned') }}</th><th class="r">{{ __('Still out') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Payment') }}</th><th></th>
    </tr></thead>
    <tbody>
    @forelse ($jobs as $j)
        <tr>
            @if ($withProcessor)<td><a class="main" href="{{ route('processors.show', $j->party_id) }}" style="color:inherit">{{ $j->processor->name }}</a><div class="meta">{{ $j->note }}</div></td>@endif
            <td>{{ $j->job_on->format('j M Y') }}@unless ($withProcessor)<div class="meta">{{ $j->note }}</div>@endunless</td>
            <td>@if ($j->workOrder)<a href="{{ route('work-orders.show', $j->workOrder) }}">{{ $j->workOrder->number }}</a>@else — @endif</td>
            <td class="r num">{{ number_format($j->plates) }}<div class="meta">× {{ number_format((float) $j->rate, 2) }}</div></td>
            <td class="r num">
                {{ number_format($j->returned_plates) }}
                @can('plates.manage')@if ($j->platesHeld() > 0)
                    <form method="POST" action="{{ route('plate-jobs.return', $j) }}" class="filters" style="flex-wrap:nowrap;justify-content:flex-end;margin-top:4px">@csrf @method('PATCH')
                        <input class="select" style="width:70px" type="number" name="returned_plates" min="0" max="{{ $j->plates }}" value="{{ $j->plates }}" required aria-label="{{ __('Plates returned') }}">
                        <input type="hidden" name="returned_on" value="{{ today()->toDateString() }}">
                        <button class="btn sm" type="submit">{{ __('Returned') }}</button>
                    </form>
                @endif @endcan
            </td>
            <td class="r num"><b>{{ number_format($j->platesHeld()) }}</b></td>
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
            <td class="r">@can('plates.manage')<a class="btn sm" href="{{ route('plate-jobs.edit', ['plateJob' => $j, 'return' => request()->fullUrl()]) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan</td>
        </tr>
    @empty
        <tr><td colspan="{{ $withProcessor ? 9 : 8 }}"><div class="empty"><b>{{ __('No plate jobs here') }}</b></div></td></tr>
    @endforelse
    </tbody>
</table></div>
