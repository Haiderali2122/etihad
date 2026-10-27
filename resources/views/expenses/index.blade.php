<x-layouts.app :title="__('Cash-out')">
    <x-page-head :title="__('Cash-out')" :subtitle="__('Cash given by the owner and where each rupee was spent.')">
        <x-slot:actions>@can('expense-heads.manage')<a class="btn" href="{{ route('expense-heads.index') }}"><x-icon name="file" />{{ __('Expense heads') }}</a>@endcan @can('expenses.create')<a class="btn primary" href="{{ route('expenses.create') }}"><x-icon name="plus" />{{ __('Add expense') }}</a>@endcan</x-slot:actions>
    </x-page-head>

    <div class="grid g4">
        <x-kpi icon="wallet" tone="warn" :label="__('Cash with manager')" :foot="__('Given minus spent')">Rs {{ number_format($cashGiven - $cashSpent) }}</x-kpi>
        <x-kpi icon="download" :label="__('Total given')">Rs {{ number_format($cashGiven) }}</x-kpi>
        <x-kpi icon="send" :label="__('Total spent')">Rs {{ number_format($cashSpent) }}</x-kpi>
        <x-kpi icon="shield" tone="good" :label="__('Spent today')" :foot="$dailyLimit ? __('Daily limit Rs :limit', ['limit' => number_format($dailyLimit)]) : __('No daily limit set — every spend needs approval')">Rs {{ number_format($todaySpent) }}</x-kpi>
    </div>

    <div class="grid g-main">
        <div class="card">
            <div class="toolbar">
                <h3 style="margin:0">{{ __('Roznamcha') }}</h3>
                <form class="filters" method="GET">
                    <select class="select" name="head" aria-label="{{ __('Head') }}" onchange="this.form.submit()">
                        <option value="">{{ __('All heads') }}</option>
                        @foreach ($heads as $h)<option value="{{ $h->id }}" @selected($headId == $h->id)>{{ $h->localName() }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Head') }}</th><th>{{ __('Paid to') }}</th><th>{{ __('Reason') }}</th><th class="r">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr>
                        <td>{{ $e->spent_on->format('j M') }}<div class="meta">{{ $e->user->name }}</div></td>
                        <td><span class="who"><i style="width:10px;height:10px;border-radius:3px;background:{{ $e->head->color }};display:inline-block"></i>{{ $e->head->localName() }}</span></td>
                        <td>{{ $e->paid_to }}</td>
                        <td>{{ $e->reason }}@if ($e->machine)<div class="meta">{{ $e->machine->code }}</div>@endif</td>
                        <td class="r num"><b>Rs {{ number_format($e->amount) }}</b></td>
                        <td><x-badge :tone="$e->status->tone()">{{ $e->status->label() }}</x-badge>@if ($e->review_note)<div class="meta">{{ $e->review_note }}</div>@endif</td>
                        <td class="r">@include('partials.attachment', ['path' => $e->attachment])</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty"><b>{{ __('No expenses yet') }}</b></div></td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $expenses->links() }}
        </div>

        <div style="display:flex;flex-direction:column;gap:18px">
            @can('give-cash')
                <form class="card" method="POST" action="{{ route('cash-floats.store') }}">
                    @csrf
                    <div class="card-h"><div><h3>{{ __('Give cash to manager') }}</h3><div class="sub">{{ __('Recorded against the manager\'s cash in hand') }}</div></div></div>
                    <div class="card-b"><div class="form" style="grid-template-columns:minmax(0,1fr)">
                        <x-field name="user_id" :label="__('Manager')" :options="$managers->pluck('name', 'id')" required />
                        <x-field name="amount" type="number" min="1" :label="__('Amount (Rs)')" required />
                        <x-field name="given_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                        <x-field name="note" :label="__('Note')" />
                        <button class="btn primary" type="submit" style="justify-content:center"><x-icon name="wallet" />{{ __('Record cash given') }}</button>
                    </div></div>
                </form>
            @endcan
            <div class="card">
                <div class="card-h"><div><h3>{{ __('Cash given') }}</h3><div class="sub">{{ __('Latest 10') }}</div></div></div>
                <div class="card-b"><div class="list">
                    @forelse ($floats as $f)
                        <div class="row"><span class="ic-tile good"><x-icon name="wallet" /></span><div class="grow"><div class="t">{{ $f->user->name }}</div><div class="s">{{ $f->given_on->format('j M Y') }}{{ $f->note ? ' — '.$f->note : '' }}</div></div><b class="num">Rs {{ number_format($f->amount) }}</b></div>
                    @empty
                        <div class="empty"><b>{{ __('No cash given yet') }}</b></div>
                    @endforelse
                </div></div>
            </div>
        </div>
    </div>
</x-layouts.app>
