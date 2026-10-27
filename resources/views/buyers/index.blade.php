<x-layouts.app :title="__('Buyers')">
    <x-page-head :title="__('Buyers')" :subtitle="__('Who the books go to: copies supplied, and advances still to be adjusted.')">
        <x-slot:actions>@can('parties.manage')<a class="btn primary" href="{{ route('parties.create') }}"><x-icon name="plus" />{{ __('Add buyer') }}</a>@endcan</x-slot:actions>
    </x-page-head>
    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Buyer') }}</th><th class="r">{{ __('Loads') }}</th><th class="r">{{ __('Copies supplied') }}</th><th>{{ __('Last load') }}</th><th class="r">{{ __('Advance left') }}</th></tr></thead>
        <tbody>
        @forelse ($buyers as $row)
            <tr>
                <td><a class="main" href="{{ route('buyers.show', $row['buyer']) }}" style="color:inherit;text-decoration:none">{{ $row['buyer']->name }}</a><div class="meta">{{ $row['buyer']->phone }}</div></td>
                <td class="r num">{{ $row['loads'] }}</td>
                <td class="r num"><b>{{ number_format($row['copies']) }}</b></td>
                <td>{{ $row['last_on'] ? \Illuminate\Support\Carbon::parse($row['last_on'])->format('j M Y') : '—' }}</td>
                <td class="r num">Rs {{ number_format($row['advance_left']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><b>{{ __('No buyers yet') }}</b>{{ __('Add buyers under Parties & suppliers with the type “Buyer”.') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
