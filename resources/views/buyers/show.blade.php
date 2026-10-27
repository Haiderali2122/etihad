<x-layouts.app :title="$buyer->name">
    <x-page-head :title="$buyer->name" :subtitle="collect([$buyer->phone, $buyer->address])->filter()->join(' · ')">
        <x-slot:actions>
            <a class="btn" href="{{ route('buyers.index') }}">{{ __('Back') }}</a>
            @can('dispatch.manage')<a class="btn primary" href="{{ route('dispatches.create') }}"><x-icon name="truck" />{{ __('New loading') }}</a>@endcan
        </x-slot:actions>
    </x-page-head>
    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif
    <div class="grid g3">
        <x-kpi icon="truck" :label="__('Copies supplied')" :foot="trans_choice(':count load|:count loads', $dispatches->count())">{{ number_format($dispatches->sum('copies')) }}</x-kpi>
        <x-kpi icon="download" tone="good" :label="__('Advances received')">Rs {{ number_format($advances) }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Advance left to adjust')" :foot="__('Rs :n adjusted', ['n' => number_format($adjusted)])">Rs {{ number_format($advances - $adjusted) }}</x-kpi>
    </div>
    <div class="grid g-main">
        <div class="card">
            <div class="card-h"><div><h3>{{ __('Loads') }}</h3></div></div>
            <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Load') }}</th><th>{{ __('Date') }}</th><th>{{ __('Book') }}</th><th class="r">{{ __('Copies') }}</th></tr></thead>
                <tbody>
                @forelse ($dispatches as $d)
                    <tr><td><a href="{{ route('dispatches.show', $d) }}">{{ $d->number }}</a></td><td>{{ $d->dispatched_on->format('j M Y') }}</td><td>{{ $d->bookName() }}</td><td class="r num"><b>{{ number_format($d->copies) }}</b></td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">{{ __('Nothing loaded yet') }}</div></td></tr>
                @endforelse
                </tbody>
            </table></div></div>
        </div>
        @can('buyers.manage')
            <form method="POST" action="{{ route('buyers.entries.store', $buyer) }}" class="card">
                @csrf
                <div class="card-h"><div><h3>{{ __('Advance or adjustment') }}</h3><div class="sub">{{ __('How advances are adjusted is still to be confirmed with the owner.') }}</div></div></div>
                <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                    <x-field name="kind" :label="__('Type')" :options="collect(\App\Enums\BuyerEntryKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])" required />
                    <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" required />
                    <x-field name="entry_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                    <x-field name="dispatch_id" :label="__('Against load (optional)')" :options="$dispatches->pluck('number', 'id')" :placeholder="__('None')" />
                    <x-field name="note" :label="__('Note')" />
                    <button class="btn primary" type="submit" style="justify-content:center"><x-icon name="check" />{{ __('Save') }}</button>
                </div></div>
            </form>
        @endcan
    </div>
    <div class="card">
        <div class="card-h"><div><h3>{{ __('Advances and adjustments') }}</h3></div></div>
        <div class="card-b" style="padding-inline:0"><div class="tbl-wrap"><table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Load') }}</th><th>{{ __('Note') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr><td>{{ $e->entry_on->format('j M Y') }}</td><td>{{ $e->kind->label() }}</td><td>{{ $e->dispatch?->number ?? '—' }}</td><td>{{ $e->note ?: '—' }}</td>
                    <td class="r num"><b>{{ $e->kind->value === 'adjustment' ? '−' : '+' }} Rs {{ number_format($e->amount) }}</b></td></tr>
            @empty
                <tr><td colspan="5"><div class="empty">{{ __('No advances yet') }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
</x-layouts.app>
