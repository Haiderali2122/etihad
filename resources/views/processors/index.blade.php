<x-layouts.app :title="__('Processors')">
    <x-page-head :title="__('Processors')" :subtitle="__('Plate processors: plates given, returned and still with them, and what was billed and paid.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('plate-jobs.index') }}"><x-icon name="file" />{{ __('Plates') }}</a>
            @can('plates.manage')<a class="btn primary" href="{{ route('processors.create') }}"><x-icon name="plus" />{{ __('Add processor') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>
    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Processor') }}</th><th class="r">{{ __('Plates given') }}</th><th class="r">{{ __('Returned') }}</th><th class="r">{{ __('Still with them') }}</th><th class="r">{{ __('Billed') }}</th><th class="r">{{ __('Paid') }}</th><th class="r">{{ __('Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($processors as $p)
            @php $t = $totals->get($p->id); $billed = (int) ($t->billed ?? 0); $paid = (int) ($t->paid ?? 0); @endphp
            <tr>
                <td><a class="main" href="{{ route('processors.show', $p) }}" style="color:inherit">{{ $p->name }}</a><div class="meta">{{ $p->phone }}</div></td>
                <td class="r num">{{ number_format((int) ($t->given ?? 0)) }}</td>
                <td class="r num">{{ number_format((int) ($t->returned ?? 0)) }}</td>
                <td class="r num"><b>{{ number_format((int) ($t->given ?? 0) - (int) ($t->returned ?? 0)) }}</b></td>
                <td class="r num">Rs {{ number_format($billed) }}</td>
                <td class="r num">Rs {{ number_format($paid) }}</td>
                <td class="r num"><b @style(['color:var(--bad)' => $billed > $paid])>Rs {{ number_format($billed - $paid) }}</b></td>
                <td><x-badge :tone="$p->is_active ? 'good' : 'mute'">{{ $p->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">@can('plates.manage')<a class="btn sm" href="{{ route('processors.edit', $p) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><b>{{ __('No processors yet') }}</b>{{ __('Add the plate processors you work with.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
