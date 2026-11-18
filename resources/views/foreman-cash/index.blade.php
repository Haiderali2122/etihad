<x-layouts.app :title="__('Foreman cash')">
    <x-page-head :title="__('Foreman cash — :month', ['month' => $month->format('F Y')])" :subtitle="__('Each time cash is given to a foreman. Cash given 10th–21st counts on the 22nd\'s advance salary screen; cash given 22nd–9th counts on the 10th\'s salaries screen. Every handout comes off the manager\'s cash.')">
        <x-slot:actions>
            <a class="btn" href="{{ route('foreman-cash.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"><x-icon name="back" />{{ $month->copy()->subMonth()->format('M Y') }}</a>
            <a class="btn" href="{{ route('foreman-cash.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ $month->copy()->addMonth()->format('M Y') }}</a>
        </x-slot:actions>
    </x-page-head>

    @can('advances.create')
        @if ($foremen->isEmpty())
            <div class="card"><div class="empty"><b>{{ __('No foremen yet') }}</b>{{ __('Tick “Foreman” on an employee\'s record first.') }}</div></div>
        @else
            <form method="POST" action="{{ route('foreman-cash.store') }}" class="card form-card">
                @csrf
                <div class="card-h"><div><h3>{{ __('Give cash to a foreman') }}</h3></div></div>
                <div class="card-b"><div class="form">
                    <x-field name="foreman_id" :label="__('Foreman')" :options="$foremen->pluck('name', 'id')" :value="$foremen->count() === 1 ? $foremen->first()->id : null" :placeholder="$foremen->count() > 1 ? __('Choose foreman') : null" required />
                    <x-field name="given_on" type="date" :label="__('Date')" :value="today()->toDateString()" required />
                    <x-field name="amount" type="number" min="1" step="1" :label="__('Amount (Rs)')" required />
                    <x-field name="note" :label="__('Note')" />
                </div></div>
                <div class="form-actions"><button class="btn primary" type="submit"><x-icon name="wallet" />{{ __('Save') }}</button></div>
            </form>
        @endif
    @endcan

    @foreach ($periods as $payday => $period)
        <div class="card">
            <div class="card-h"><div>
                <h3>{{ $payday === \App\Models\ForemanCash::Advance ? __('For the 22nd (advance salary)') : __('For the 10th (salaries)') }}</h3>
                <div class="sub">{{ $period['window']['from']->format('j M') }} – {{ $period['window']['to']->format('j M Y') }}</div>
            </div>
                <a class="btn sm" href="{{ $payday === \App\Models\ForemanCash::Advance ? route('salary-advances.mid-month', ['month' => $month->format('Y-m')]) : route('payroll.tenth') }}">{{ $payday === \App\Models\ForemanCash::Advance ? __('Advance salary (22nd)') : __('Salaries (10th)') }} →</a>
            </div>
            <div class="tbl-wrap"><table>
                <thead><tr><th style="width:44px">#</th><th>{{ __('Date') }}</th><th>{{ __('Foreman') }}</th><th>{{ __('Note') }}</th><th class="r">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($period['rows'] as $row)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td>{{ $row->given_on->format('j M Y') }}</td>
                        <td>{{ $row->foreman?->name }}</td>
                        <td>{{ $row->note ?: '—' }}</td>
                        <td class="r num"><b>Rs {{ number_format($row->amount) }}</b></td>
                        <td class="r" style="white-space:nowrap">@can('advances.create')<a class="btn sm" href="{{ route('foreman-cash.edit', $row) }}"><x-icon name="edit" />{{ __('Edit') }}</a> <x-delete-button :action="route('records.destroy', ['foreman-cash', $row->id])" :label="''" small aria-label="{{ __('Delete') }}" />@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">{{ __('No cash given in this period yet') }}</div></td></tr>
                @endforelse
                </tbody>
                @if ($period['rows']->isNotEmpty())
                    <tfoot>
                        @foreach ($period['rows']->groupBy('foreman_id') as $rows)
                            <tr><td></td><td colspan="3"><b>{{ __('Total to :name', ['name' => $rows->first()->foreman?->name]) }}</b></td><td class="r num"><b>Rs {{ number_format($rows->sum('amount')) }}</b></td><td></td></tr>
                        @endforeach
                    </tfoot>
                @endif
            </table></div>
        </div>
    @endforeach
</x-layouts.app>
