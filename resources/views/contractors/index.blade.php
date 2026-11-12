<x-layouts.app :title="__('Folding & binding')">
    <x-page-head :title="__('Folding & binding')" :subtitle="__('Contractors, the work given to them and their hisab.')">
        <x-slot:actions>
            @can('contractors.manage')
                <a class="btn" href="{{ route('contractors.create') }}"><x-icon name="plus" />{{ __('Add contractor') }}</a>
                <a class="btn primary" href="{{ route('contractor-jobs.create') }}"><x-icon name="fold" />{{ __('Give job') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-head>

    @if ($errors->any())<div class="flash err" role="alert"><x-icon name="alert" />{{ $errors->first() }}</div>@endif

    <div class="grid g3">
        <x-kpi icon="fold" tone="bad" :label="__('Owed to contractors')">Rs {{ number_format($owed) }}</x-kpi>
        <x-kpi icon="wallet" tone="warn" :label="__('Advanced, not yet earned')">Rs {{ number_format($advanced) }}</x-kpi>
        <x-kpi icon="clock" :label="__('Jobs in progress')">{{ $contractors->sum('open_jobs') }}</x-kpi>
    </div>

    <div class="card"><div class="tbl-wrap"><table>
        <thead><tr><th>{{ __('Contractor') }}</th><th>{{ __('Work') }}</th><th class="r">{{ __('Earned') }}</th><th class="r">{{ __('Paid') }}</th><th class="r">{{ __('Balance') }}</th><th class="r">{{ __('Open jobs') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($contractors as $c)
            @php $balance = (int) $c->jobs_sum_amount - (int) $c->payments_sum_amount; @endphp
            {{-- Clicking a row opens the contractor, with its jobs and payments to edit. --}}
            <tr class="row-link" data-href="{{ route('contractors.show', $c) }}" style="cursor:pointer">
                <td><a class="main" href="{{ route('contractors.show', $c) }}" style="color:inherit;text-decoration:none">{{ $c->localName() }}</a><div class="meta">{{ $c->phone }}</div></td>
                <td>{{ $c->work->label() }}</td>
                <td class="r num">Rs {{ number_format($c->jobs_sum_amount ?? 0) }}</td>
                <td class="r num">Rs {{ number_format($c->payments_sum_amount ?? 0) }}</td>
                <td class="r num"><b @style(['color:var(--bad)' => $balance > 0, 'color:var(--warn)' => $balance < 0])>{{ $balance < 0 ? __('Advance Rs :n', ['n' => number_format(-$balance)]) : 'Rs '.number_format($balance) }}</b></td>
                <td class="r num">{{ $c->open_jobs }}</td>
                <td><x-badge :tone="$c->is_active ? 'good' : 'mute'">{{ $c->is_active ? __('Active') : __('Inactive') }}</x-badge></td>
                <td class="r" style="white-space:nowrap">@can('contractors.manage')<a class="btn sm" href="{{ route('contractors.show', ['contractor' => $c, 'kind' => 'advance']) }}#pay">{{ __('Advance') }}</a> <a class="btn sm primary" href="{{ route('contractors.show', ['contractor' => $c, 'kind' => 'payment']) }}#pay"><x-icon name="wallet" />{{ __('Pay') }}</a> <a class="btn sm" href="{{ route('contractors.edit', $c) }}" aria-label="{{ __('Edit :name', ['name' => $c->name]) }}"><x-icon name="edit" />{{ __('Edit') }}</a>@endcan @can('contractors.manage')<x-delete-button :action="route('contractors.destroy', $c)" :confirm="__('Delete :name?', ['name' => $c->name])" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><b>{{ __('No contractors yet') }}</b></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
