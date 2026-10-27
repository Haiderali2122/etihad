<x-layouts.app :title="__('Paper types')">
    <x-page-head :title="__('Paper types')" :subtitle="__('Every paper type and size kept in the godown, with the level at which to reorder.')">
        <x-slot:actions><a class="btn primary" href="{{ route('paper-types.create') }}"><x-icon name="plus" />{{ __('Add paper type') }}</a></x-slot:actions>
    </x-page-head>
    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Size') }}</th><th class="r">GSM</th><th>{{ __('Unit') }}</th><th class="r">{{ __('Reorder at') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @foreach ($paperTypes as $type)
            <tr>
                <td><b>{{ $type->code }}</b></td>
                <td>{{ $type->name }}@if ($type->name_ur)<div class="meta t-ur" style="font-family:var(--urdu)">{{ $type->name_ur }}</div>@endif</td>
                <td>{{ $type->size }}</td>
                <td class="r num">{{ $type->gsm }}</td>
                <td>{{ __($type->unit) }} <span class="meta">({{ $type->sheets_per_unit }} {{ __('sheets') }})</span></td>
                <td class="r num">{{ number_format($type->reorder_level) }}</td>
                <td><x-badge :tone="$type->is_active ? 'good' : 'mute'">{{ $type->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r"><a class="btn sm" href="{{ route('paper-types.edit', $type) }}"><x-icon name="edit" />{{ __('Edit') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
</x-layouts.app>
