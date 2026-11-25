{{-- Plate bills as plain rows: plates given / returned / still out, the bill, paid and left. Payments and returns are
     entered in their own forms on the Plates tab; a row opens its full record (with each payment and return). Used on
     the Plates tab and on each processor's page, so both show the same records. --}}
<div class="tbl-wrap"><table>
    <thead><tr>
        <th>{{ __('Date') }}</th>@if ($withProcessor)<th>{{ __('Processor') }}</th>@endif<th>{{ __('Book') }}</th>
        <th class="r">{{ __('Plates') }}</th><th class="r">{{ __('Returned') }}</th><th class="r">{{ __('Still out') }}</th>
        <th class="r">{{ __('Bill') }}</th><th class="r">{{ __('Paid') }}</th><th class="r">{{ __('Left') }}</th><th></th>
    </tr></thead>
    <tbody>
    @forelse ($jobs as $j)
        @php $left = $j->unpaid(); @endphp
        <tr data-href="{{ route('records.show', ['plate-job', $j->id]) }}">
            <td><bdi dir="ltr">{{ $j->job_on->format('j M Y') }}</bdi></td>
            @if ($withProcessor)<td><b>{{ $j->processor->name }}</b></td>@endif
            <td>{{ $j->book_name ?: '—' }}@if ($j->workOrder)<div class="meta">{{ $j->workOrder->number }}</div>@endif @if ($j->note)<div class="meta">{{ $j->note }}</div>@endif</td>
            <td class="r num">{{ number_format($j->plates) }}<div class="meta"><bdi dir="ltr">@if ($j->plate_size){{ $j->plate_size }} · @endif Rs {{ rtrim(rtrim(number_format((float) $j->rate, 2), '0'), '.') }}</bdi></div></td>
            <td class="r num">{{ number_format($j->returned_plates) }}</td>
            <td class="r num"><b @style(['color:var(--bad)' => $j->platesHeld() > 0])>{{ number_format($j->platesHeld()) }}</b></td>
            <td class="r num"><b>Rs {{ number_format($j->amount) }}</b></td>
            <td class="r num">Rs {{ number_format($j->paid_amount) }}</td>
            <td class="r num">@if ($left > 0)<b style="color:var(--bad)">Rs {{ number_format($left) }}</b>@else<x-badge tone="good">{{ __('Paid') }}</x-badge>@endif</td>
            <td class="r" style="white-space:nowrap">@can('plates.manage')<a class="btn sm" href="{{ route('plate-jobs.edit', ['plateJob' => $j, 'return' => request()->fullUrl()]) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                <x-delete-button :action="route('plate-jobs.destroy', $j)" :confirm="$j->paid_amount > 0 ? __('Delete this plate job? Its payments will also be removed from Cash-out.') : __('Delete this plate job?')" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
        </tr>
    @empty
        <tr><td colspan="{{ $withProcessor ? 10 : 9 }}"><div class="empty"><b>{{ __('No plate jobs here') }}</b></div></td></tr>
    @endforelse
    </tbody>
</table></div>
