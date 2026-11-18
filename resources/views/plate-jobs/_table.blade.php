{{-- Plate records with their returns and payment. Used on the Plates tab and on each processor's page, so both show the same records. --}}
<div class="tbl-wrap"><table>
    <thead><tr>
        @if ($withProcessor)<th>{{ __('Processor') }}</th>@endif
        <th>{{ __('Date') }}</th><th>{{ __('Book') }}</th><th class="r">{{ __('Given') }}</th><th class="r">{{ __('Returned') }}</th><th class="r">{{ __('Still out') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Payment') }}</th><th></th>
    </tr></thead>
    <tbody>
    @forelse ($jobs as $j)
        <tr>
            @if ($withProcessor)<td><a class="main" href="{{ route('processors.show', $j->party_id) }}" style="color:inherit">{{ $j->processor->name }}</a><div class="meta">{{ $j->note }}</div></td>@endif
            <td>{{ $j->job_on->format('j M Y') }}@unless ($withProcessor)<div class="meta">{{ $j->note }}</div>@endunless</td>
            <td>{{ $j->book_name ?: '—' }}@if ($j->workOrder)<div class="meta"><a href="{{ route('work-orders.show', $j->workOrder) }}">{{ $j->workOrder->number }}</a></div>@endif</td>
            <td class="r num">{{ number_format($j->plates) }}<div class="meta">@if ($j->plate_size){{ $j->plate_size }} · @endif× {{ number_format((float) $j->rate, 2) }}</div></td>
            <td class="r num">
                <b>{{ number_format($j->returned_plates) }}</b>
                {{-- Each return with its date. --}}
                @foreach ($j->returns as $r)
                    <div class="meta">{{ $r->returned_on->format('j M') }}: {{ number_format($r->plates) }}</div>
                @endforeach
                @can('plates.manage')@if ($j->platesHeld() > 0)
                    <form method="POST" action="{{ route('plate-jobs.return', $j) }}" class="filters" style="flex-wrap:nowrap;justify-content:flex-end;margin-top:4px">@csrf @method('PATCH')
                        <input class="select" style="width:64px" type="number" name="returned_plates" min="1" max="{{ $j->platesHeld() }}" value="{{ $j->platesHeld() }}" required aria-label="{{ __('Plates returned') }}">
                        <input class="select" style="width:130px" type="date" name="returned_on" value="{{ today()->toDateString() }}" min="{{ $j->job_on->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ __('Returned on') }}">
                        <button class="btn sm" type="submit">{{ __('Returned') }}</button>
                    </form>
                @endif @endcan
            </td>
            <td class="r num"><b>{{ number_format($j->platesHeld()) }}</b></td>
            <td class="r num"><b>Rs {{ number_format($j->amount) }}</b>@if ($j->paid_amount > 0 && $j->unpaid() > 0)<div class="meta">{{ __('Paid Rs :n', ['n' => number_format($j->paid_amount)]) }}</div>@endif</td>
            <td>@include('partials.bill-payments', ['payable' => $j, 'payUrl' => route('plate-jobs.pay', $j), 'from' => $j->job_on, 'canPay' => auth()->user()->can('plates.manage')])</td>
            <td class="r" style="white-space:nowrap">@can('plates.manage')<a class="btn sm" href="{{ route('plate-jobs.edit', ['plateJob' => $j, 'return' => request()->fullUrl()]) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                <x-delete-button :action="route('plate-jobs.destroy', $j)" :confirm="$j->paid_amount > 0 ? __('Delete this plate job? Its payments will also be removed from Cash-out.') : __('Delete this plate job?')" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
        </tr>
    @empty
        <tr><td colspan="{{ $withProcessor ? 9 : 8 }}"><div class="empty"><b>{{ __('No plate jobs here') }}</b></div></td></tr>
    @endforelse
    </tbody>
</table></div>
