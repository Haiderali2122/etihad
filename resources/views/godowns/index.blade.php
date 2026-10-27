<x-layouts.app :title="__('Godowns')">
    <x-page-head :title="__('Godowns')" :subtitle="__('Places trucks are unloaded into. They appear under “Unloaded in” when receiving a truck.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('paper.index') }}">{{ __('Back to paper stock') }}</a>
            <a class="btn primary" href="{{ route('godowns.create') }}"><x-icon name="plus" />{{ __('Add godown') }}</a>
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Godown') }}</th><th>{{ __('Address') }}</th><th class="r">{{ __('Trucks') }}</th><th class="r">{{ __('Rims received') }}</th><th>{{ __('Last truck') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($godowns as $godown)
            <tr>
                <td><div class="main">{{ $godown->name }}</div>@if ($godown->name_ur)<div class="meta" style="font-family:var(--urdu)" dir="rtl">{{ $godown->name_ur }}</div>@endif</td>
                <td>{{ $godown->address ?: '—' }}</td>
                <td class="r num">{{ number_format($godown->paper_receipts_count) }}</td>
                <td class="r num"><b>{{ number_format($godown->paper_receipts_sum_quantity ?? 0) }}</b></td>
                <td>{{ $godown->paper_receipts_max_received_at ? \Illuminate\Support\Carbon::parse($godown->paper_receipts_max_received_at)->format('j M Y') : '—' }}</td>
                <td><x-badge :tone="$godown->is_active ? 'good' : 'mute'">{{ $godown->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">
                    <a class="btn sm" href="{{ route('godowns.edit', $godown) }}"><x-icon name="edit" />{{ __('Edit') }}</a>
                    @if ($godown->paper_receipts_count === 0)
                        <form method="POST" action="{{ route('godowns.destroy', $godown) }}" style="display:inline" onsubmit="return confirm(@js(__('Delete :name?', ['name' => $godown->name])))">
                            @csrf @method('DELETE')
                            <button class="btn sm" type="submit" style="color:var(--bad)" aria-label="{{ __('Delete :name', ['name' => $godown->name]) }}"><x-icon name="x" /></button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><b>{{ __('No godowns yet') }}</b>{{ __('Add one before receiving a truck.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
