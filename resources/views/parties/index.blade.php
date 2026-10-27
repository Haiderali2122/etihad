<x-layouts.app :title="__('Parties & suppliers')">
    <x-page-head :title="__('Parties & suppliers')" :subtitle="__('Paper parties and mills, material suppliers, mechanics and outside presses.')">
        <x-slot:actions><a class="btn primary" href="{{ route('parties.create') }}"><x-icon name="plus" />{{ __('Add party') }}</a></x-slot:actions>
    </x-page-head>
    <div class="card">
        <div class="toolbar"><div class="tabs">
            <a href="{{ route('parties.index') }}" @class(['tab', 'on' => ! $kind]) style="text-decoration:none">{{ __('All') }}</a>
            @foreach (\App\Enums\PartyKind::cases() as $k)
                <a href="{{ route('parties.index', ['kind' => $k->value]) }}" @class(['tab', 'on' => $kind === $k]) style="text-decoration:none">{{ $k->label() }}</a>
            @endforeach
        </div></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Phone') }}</th><th class="r">{{ __('Purchases') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($parties as $party)
                <tr>
                    <td><div class="main">{{ $party->name }}</div><div class="meta">{{ $party->address }}</div></td>
                    <td>{{ $party->kind->label() }}</td>
                    <td>{{ $party->phone }}</td>
                    <td class="r num">{{ $party->purchases_sum_amount ? 'Rs '.number_format($party->purchases_sum_amount) : '—' }}</td>
                    <td><x-badge :tone="$party->is_active ? 'good' : 'mute'">{{ $party->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                    <td class="r"><a class="btn sm" href="{{ route('parties.edit', $party) }}"><x-icon name="edit" />{{ __('Edit') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><b>{{ __('No parties yet') }}</b></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
